<?php

namespace App\Domains\Assets\Actions;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\DepreciationCalculator;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a monthly depreciation entry for a fixed asset and posts the journal entry.
 */
class DepreciateAssetAction
{
    public function __construct(
        private LedgerService $ledger,
        private DepreciationCalculator $calculator,
    ) {}

    public function execute(FixedAsset $asset, ?Carbon $periodDate = null): ?DepreciationEntry
    {
        if (! $asset->is_active || $asset->disposed_at || $asset->isFullyDepreciated()) {
            return null;
        }

        $periodDate ??= Carbon::now();
        $monthlyAmount = $this->calculator->monthlyAmount($asset);

        if (Money::compare($monthlyAmount, '0') <= 0) {
            return null;
        }

        // Ensure we don't depreciate below salvage value
        $remainingDepreciable = Money::subtract($asset->netBookValue(), $asset->salvage_value);
        if (Money::compare($monthlyAmount, $remainingDepreciable) > 0) {
            $monthlyAmount = $remainingDepreciable;
        }

        if (Money::compare($monthlyAmount, '0') <= 0) {
            return null;
        }

        $entry = new JournalEntryData(
            date: $periodDate->toDateString(),
            reference: 'DEP-'.$asset->id.'-'.$periodDate->format('Y-m'),
            description: "Depreciation: {$asset->name} ({$periodDate->format('M Y')})",
            lines: [
                new JournalLineData(
                    accountId: (string) $asset->depreciation_expense_account_id,
                    debit: $monthlyAmount,
                    credit: '0',
                    description: "Depreciation expense: {$asset->name}",
                ),
                new JournalLineData(
                    accountId: (string) $asset->accumulated_depreciation_account_id,
                    debit: '0',
                    credit: $monthlyAmount,
                    description: "Accumulated depreciation: {$asset->name}",
                ),
            ],
        );

        return DB::transaction(function () use ($asset, $entry, $monthlyAmount, $periodDate): DepreciationEntry {
            $journalEntry = $this->ledger->postEntry($asset->organization_id, $entry);

            return DepreciationEntry::create([
                'fixed_asset_id' => $asset->id,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $monthlyAmount,
                'period_date' => $periodDate->toDateString(),
                'created_at' => now(),
            ]);
        });
    }
}
