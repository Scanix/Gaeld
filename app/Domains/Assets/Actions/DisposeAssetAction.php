<?php

namespace App\Domains\Assets\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Models\FixedAsset;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Disposes of a fixed asset: records the disposal journal entry and deactivates the asset.
 */
class DisposeAssetAction
{
    public function __construct(
        private LedgerService $ledger,
        private LedgerQueryService $ledgerQuery,
    ) {}

    public function execute(FixedAsset $asset, string $disposalAmount, Carbon $disposalDate): FixedAsset
    {
        $totalDepreciated = $asset->totalDepreciated();
        $nbv = $asset->netBookValue();

        // Gain or loss: disposal proceeds - net book value
        $gainLoss = Money::subtract($disposalAmount, $nbv);
        $isGain = ! Money::isNegative($gainLoss);

        $lines = [];

        // Debit accumulated depreciation account (remove accumulated depr.)
        if (Money::isPositive($totalDepreciated)) {
            $lines[] = new JournalLineData(
                accountId: (string) $asset->accumulated_depreciation_account_id,
                debit: $totalDepreciated,
                credit: '0',
                description: "Remove accumulated depreciation: {$asset->name}",
            );
        }

        // Debit bank for disposal proceeds
        if (Money::isPositive($disposalAmount)) {
            $bankAccount = $this->ledgerQuery->resolveAccount($asset->organization_id, AccountCode::BANK_CASH);
            $lines[] = new JournalLineData(
                accountId: (string) $bankAccount->id,
                debit: $disposalAmount,
                credit: '0',
                description: "Disposal proceeds: {$asset->name}",
            );
        }

        // Credit asset account for original purchase amount
        $lines[] = new JournalLineData(
            accountId: (string) $asset->asset_account_id,
            debit: '0',
            credit: $asset->purchase_amount,
            description: "Remove asset: {$asset->name}",
        );

        // Gain or loss
        $absGainLoss = Money::isNegative($gainLoss)
            ? Money::negate($gainLoss)
            : $gainLoss;

        if (Money::isPositive($absGainLoss)) {
            if ($isGain) {
                $gainAccount = $this->ledgerQuery->resolveAccount($asset->organization_id, AccountCode::ASSET_DISPOSAL_GAIN);
                $lines[] = new JournalLineData(
                    accountId: (string) $gainAccount->id,
                    debit: '0',
                    credit: $absGainLoss,
                    description: "Gain on disposal: {$asset->name}",
                );
            } else {
                $lossAccount = $this->ledgerQuery->resolveAccount($asset->organization_id, AccountCode::ASSET_DISPOSAL_LOSS);
                $lines[] = new JournalLineData(
                    accountId: (string) $lossAccount->id,
                    debit: $absGainLoss,
                    credit: '0',
                    description: "Loss on disposal: {$asset->name}",
                );
            }
        }

        $entry = new JournalEntryData(
            date: $disposalDate->toDateString(),
            reference: 'DISP-'.$asset->id,
            description: "Disposal of asset: {$asset->name}",
            lines: $lines,
        );

        return DB::transaction(function () use ($asset, $entry, $disposalDate, $disposalAmount): FixedAsset {
            $this->ledger->postEntry($asset->organization_id, $entry);

            $asset->update([
                'disposed_at' => $disposalDate,
                'disposal_amount' => $disposalAmount,
                'is_active' => false,
            ]);

            return $asset->fresh();
        });
    }
}
