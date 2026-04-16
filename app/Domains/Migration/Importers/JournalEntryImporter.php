<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\JournalEntryImportRow;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::JournalEntries;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];
        $accountCodes = Account::where('organization_id', $organization->id)
            ->pluck('code')
            ->flip();

        foreach ($rows as $row) {
            if (! $row instanceof JournalEntryImportRow || ! $row->isValid()) {
                continue;
            }

            if (empty($row->lines)) {
                $errors[$row->sourceRow()] = ['Journal entry must have at least one line'];

                continue;
            }

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($row->lines as $line) {
                if (! $accountCodes->has($line['account_code'])) {
                    $errors[$row->sourceRow()][] = "Account {$line['account_code']} not found";
                }
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                $errors[$row->sourceRow()][] = "Entry is not balanced: debits ({$totalDebit}) ≠ credits ({$totalCredit})";
            }
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
        $failed = 0;
        $errors = [];

        $accounts = Account::where('organization_id', $organization->id)
            ->pluck('id', 'code');

        foreach ($rows as $row) {
            if (! $row instanceof JournalEntryImportRow || ! $row->isValid()) {
                $skipped++;

                continue;
            }

            // Check all account codes exist
            $allCodesExist = true;
            foreach ($row->lines as $line) {
                if (! $accounts->has($line['account_code'])) {
                    $allCodesExist = false;

                    break;
                }
            }

            if (! $allCodesExist) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $organization, $accounts, &$imported): void {
                    $journalEntry = JournalEntry::create([
                        'organization_id' => $organization->id,
                        'date' => $row->date,
                        'reference' => $row->reference,
                        'description' => $row->description,
                    ]);

                    foreach ($row->lines as $line) {
                        TransactionLine::create([
                            'journal_entry_id' => $journalEntry->id,
                            'account_id' => $accounts->get($line['account_code']),
                            'debit' => (float) ($line['debit'] ?? 0),
                            'credit' => (float) ($line['credit'] ?? 0),
                            'description' => $line['description'] ?? null,
                        ]);
                    }

                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $rowNum = $row->sourceRow();
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::warning('Migration import: journal entry row failed', [
                    'row' => $rowNum,
                    'error' => $e->getMessage(),
                    'organization_id' => $organization->id,
                ]);
            }
        }

        if ($imported === 0 && $failed > 0) {
            return ImportResult::failure($this->dataType(), $errors, failed: $failed);
        }

        return ImportResult::success($this->dataType(), $imported, $skipped, warnings: $errors, failed: $failed);
    }
}
