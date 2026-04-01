<?php

namespace App\Domains\Migration\Parsers;

use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ExpenseImportRow;
use App\Domains\Migration\DTOs\FixedAssetImportRow;
use App\Domains\Migration\DTOs\InvoiceImportRow;
use App\Domains\Migration\DTOs\JournalEntryImportRow;
use App\Domains\Migration\DTOs\OpeningBalanceRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Parses generic CSV files with user-defined column mapping.
 *
 * Supports all data types — the user maps columns to fields interactively
 * via the CsvColumnMappingModal in the UI. The mapping is passed in as
 * extra metadata through the request.
 */
class GenericCsvParser implements PlatformParserInterface
{
    /** @var array<string, int> */
    private array $columnMapping = [];

    private string $delimiter = ',';

    public function platform(): Platform
    {
        return Platform::GenericCsv;
    }

    public function labelKey(): string
    {
        return 'migration.platforms.generic_csv.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.platforms.generic_csv.description';
    }

    public function supportedDataTypes(): array
    {
        return DataType::cases();
    }

    public function acceptedExtensions(): array
    {
        return ['csv', 'txt', 'tsv'];
    }

    /**
     * Configure the column mapping before parsing.
     *
     * @param  array<string, int>  $mapping  Field name → column index (0-based)
     */
    public function setColumnMapping(array $mapping): void
    {
        $this->columnMapping = $mapping;
    }

    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        $content = $file->get();
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));

        if (count($lines) < 2) {
            return collect();
        }

        // Skip header row
        $headers = str_getcsv(array_shift($lines), $this->delimiter);

        $rows = collect();
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line, $this->delimiter);
            $mapped = $this->applyMapping($values);

            $importRow = $this->createRow($mapped, $index + 1, $dataType);
            $rows->push($importRow);
        }

        return $rows;
    }

    public function detectDataType(UploadedFile $file): ?DataType
    {
        // Generic CSV cannot auto-detect — user must specify
        return null;
    }

    /**
     * Extract the CSV headers for the column mapping UI.
     *
     * @return string[]
     */
    public function extractHeaders(UploadedFile $file): array
    {
        $content = $file->get();
        $firstLine = strtok($content, "\n");

        if ($firstLine === false) {
            return [];
        }

        return str_getcsv(trim($firstLine), $this->delimiter);
    }

    /**
     * @return array<string, ?string>
     */
    private function applyMapping(array $values): array
    {
        $mapped = [];
        foreach ($this->columnMapping as $field => $columnIndex) {
            $mapped[$field] = $values[$columnIndex] ?? null;
        }

        return $mapped;
    }

    private function createRow(array $data, int $sourceRow, DataType $dataType): ImportRowInterface
    {
        return match ($dataType) {
            DataType::Accounts => new AccountImportRow(
                sourceRow: $sourceRow,
                code: $data['code'] ?? '',
                name: $data['name'] ?? '',
                type: $data['type'] ?? 'asset',
                description: $data['description'] ?? null,
            ),
            DataType::Contacts => new ContactImportRow(
                sourceRow: $sourceRow,
                type: $data['type'] ?? 'customer',
                name: $data['name'] ?? '',
                email: $data['email'] ?? null,
                phone: $data['phone'] ?? null,
                address: $data['address'] ?? null,
                zip: $data['zip'] ?? null,
                city: $data['city'] ?? null,
                country: $data['country'] ?? null,
            ),
            DataType::Invoices => new InvoiceImportRow(
                sourceRow: $sourceRow,
                number: $data['number'] ?? '',
                date: $data['date'] ?? '',
                dueDate: $data['due_date'] ?? null,
                status: $data['status'] ?? 'draft',
                customerName: $data['customer_name'] ?? '',
                totalAmount: $data['total_amount'] ?? null,
            ),
            DataType::Expenses => new ExpenseImportRow(
                sourceRow: $sourceRow,
                date: $data['date'] ?? '',
                amount: $data['amount'] ?? '0',
                description: $data['description'] ?? null,
                category: $data['category'] ?? null,
                supplierName: $data['supplier_name'] ?? null,
                accountCode: $data['account_code'] ?? null,
            ),
            DataType::JournalEntries => new JournalEntryImportRow(
                sourceRow: $sourceRow,
                date: $data['date'] ?? '',
                reference: $data['reference'] ?? null,
                description: $data['description'] ?? null,
                lines: $this->buildJournalLines($data),
            ),
            DataType::OpeningBalances => new OpeningBalanceRow(
                sourceRow: $sourceRow,
                accountCode: $data['account_code'] ?? '',
                accountName: $data['account_name'] ?? null,
                debit: $data['debit'] ?? null,
                credit: $data['credit'] ?? null,
            ),
            DataType::FixedAssets => new FixedAssetImportRow(
                sourceRow: $sourceRow,
                name: $data['name'] ?? '',
                acquisitionDate: $data['acquisition_date'] ?? '',
                acquisitionCost: $data['acquisition_cost'] ?? '0',
                depreciationMethod: $data['depreciation_method'] ?? 'linear',
                depreciationRate: isset($data['depreciation_rate']) ? (float) $data['depreciation_rate'] : null,
                accumulatedDepreciation: $data['accumulated_depreciation'] ?? null,
            ),
            DataType::YearEndClosing => new OpeningBalanceRow(
                sourceRow: $sourceRow,
                accountCode: $data['account_code'] ?? '',
                accountName: $data['account_name'] ?? null,
                debit: $data['debit'] ?? null,
                credit: $data['credit'] ?? null,
            ),
        };
    }

    /**
     * @return array<array{account_code: string, debit: ?string, credit: ?string, description: ?string}>
     */
    private function buildJournalLines(array $data): array
    {
        $lines = [];

        if (! empty($data['debit_account']) && ! empty($data['amount'])) {
            $lines[] = [
                'account_code' => $data['debit_account'],
                'debit' => $data['amount'],
                'credit' => null,
                'description' => $data['description'] ?? null,
            ];
        }
        if (! empty($data['credit_account']) && ! empty($data['amount'])) {
            $lines[] = [
                'account_code' => $data['credit_account'],
                'debit' => null,
                'credit' => $data['amount'],
                'description' => $data['description'] ?? null,
            ];
        }

        return $lines;
    }
}
