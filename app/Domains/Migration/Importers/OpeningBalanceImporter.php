<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\OpeningBalanceRow;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OpeningBalanceImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::OpeningBalances;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];
        $totalDebit = 0;
        $totalCredit = 0;

        $accountCodes = Account::where('organization_id', $organization->id)
            ->pluck('code')
            ->flip();

        foreach ($rows as $row) {
            if (! $row instanceof OpeningBalanceRow || ! $row->isValid()) {
                continue;
            }

            if (! $accountCodes->has($row->accountCode)) {
                $errors[$row->sourceRow()] = ["Account {$row->accountCode} not found in chart of accounts"];
            }

            $totalDebit += (float) ($row->debit ?? 0);
            $totalCredit += (float) ($row->credit ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            $errors[0] = ["Opening balances are not balanced: debits ({$totalDebit}) ≠ credits ({$totalCredit})"];
        }

        if (! empty($errors)) {
            return ValidationResult::failure([], $errors);
        }

        return ValidationResult::success();
    }

    public function import(Collection $rows, Organization $organization): ImportResult
    {
        $imported = 0;
        $skipped = 0;

        $accounts = Account::where('organization_id', $organization->id)
            ->pluck('id', 'code');

        DB::transaction(function () use ($rows, $organization, $accounts, &$imported, &$skipped): void {
            $journalEntry = JournalEntry::create([
                'organization_id' => $organization->id,
                'date' => now()->startOfYear(),
                'reference' => 'OPENING',
                'description' => __('migration.opening_balances_entry'),
                'is_opening' => true,
            ]);

            foreach ($rows as $row) {
                if (! $row instanceof OpeningBalanceRow || ! $row->isValid()) {
                    $skipped++;

                    continue;
                }

                $accountId = $accounts->get($row->accountCode);
                if (! $accountId) {
                    $skipped++;

                    continue;
                }

                $debit = (float) ($row->debit ?? 0);
                $credit = (float) ($row->credit ?? 0);

                if ($debit == 0 && $credit == 0) {
                    $skipped++;

                    continue;
                }

                TransactionLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $accountId,
                    'debit' => $debit > 0 ? $debit : 0,
                    'credit' => $credit > 0 ? $credit : 0,
                    'description' => $row->accountName,
                ]);

                $imported++;
            }
        });

        return ImportResult::success($this->dataType(), $imported, $skipped);
    }
}
