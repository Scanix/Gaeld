<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ExpenseImportRow;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::Expenses;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts, DataType::Contacts];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];

        foreach ($rows as $row) {
            if (! $row instanceof ExpenseImportRow || ! $row->isValid()) {
                continue;
            }

            if (empty($row->date)) {
                $errors[$row->sourceRow()][] = 'Expense date is required';
            }

            if (empty($row->amount) || (float) $row->amount <= 0) {
                $errors[$row->sourceRow()][] = 'Expense amount must be positive';
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

        foreach ($rows as $row) {
            if (! $row instanceof ExpenseImportRow || ! $row->isValid()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $organization, &$imported): void {
                    // Link or create supplier
                    $supplier = null;
                    if ($row->supplierName) {
                        $supplier = Contact::where('organization_id', $organization->id)
                            ->where('name', $row->supplierName)
                            ->first();

                        if (! $supplier) {
                            $supplier = Contact::create([
                                'organization_id' => $organization->id,
                                'name' => $row->supplierName,
                                'email' => $row->supplierEmail,
                                'country' => 'CH',
                            ]);
                        }
                    }

                    Expense::create([
                        'organization_id' => $organization->id,
                        'supplier_id' => $supplier?->id,
                        'date' => $row->date,
                        'amount' => $row->amount,
                        'currency' => $row->currency,
                        'description' => $row->description,
                        'category' => $row->category,
                        'reference' => $row->reference,
                        'is_paid' => $row->isPaid,
                    ]);

                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $rowNum = $row->sourceRow();
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::warning('Migration import: expense row failed', [
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
