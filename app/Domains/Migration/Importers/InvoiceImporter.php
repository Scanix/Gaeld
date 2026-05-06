<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\InvoiceImportRow;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::Invoices;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts, DataType::Contacts];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];

        foreach ($rows as $row) {
            if (! $row instanceof InvoiceImportRow || ! $row->isValid()) {
                continue;
            }

            if (empty($row->number)) {
                $errors[$row->sourceRow()][] = 'Invoice number is required';
            }

            if (empty($row->date)) {
                $errors[$row->sourceRow()][] = 'Invoice date is required';
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
            if (! $row instanceof InvoiceImportRow || ! $row->isValid()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $organization, &$imported, &$skipped): void {
                    // Deduplicate by number within same org
                    $existing = Invoice::where('organization_id', $organization->id)
                        ->where('number', $row->number)
                        ->exists();

                    if ($existing) {
                        $skipped++;

                        return;
                    }

                    // Find or create customer
                    $customer = null;
                    if ($row->customerName) {
                        $customer = Contact::where('organization_id', $organization->id)
                            ->where('name', $row->customerName)
                            ->first();

                        if (! $customer) {
                            $customer = Contact::create([
                                'organization_id' => $organization->id,
                                'name' => $row->customerName,
                                'email' => $row->customerEmail,
                                'country' => 'CH',
                            ]);
                        }
                    }

                    Invoice::create([
                        'organization_id' => $organization->id,
                        'customer_id' => $customer?->id,
                        'number' => $row->number,
                        'date' => $row->date,
                        'due_date' => $row->dueDate ?? now()->addDays(30)->toDateString(),
                        'status' => $row->status,
                        'currency' => $row->currency,
                        'description' => $row->description,
                        'total_amount' => $row->totalAmount ?? '0.00',
                        'reference' => $row->reference,
                    ]);

                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $rowNum = $row->sourceRow();
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::warning('Migration import: invoice row failed', [
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
