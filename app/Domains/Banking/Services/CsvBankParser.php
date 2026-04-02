<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Services\Camt\CamtEntry;

/**
 * Parses CSV bank statement files into CamtEntry DTOs.
 *
 * Supports user-defined column mapping for maximum compatibility
 * with different bank CSV export formats.
 */
class CsvBankParser
{
    /** @var CamtEntry[] */
    private array $entries = [];

    // ──────────────────────────────────────────────────────────────
    //  Parsing
    // ──────────────────────────────────────────────────────────────

    /**
     * Parse CSV content with the given column mapping.
     *
     * @param  array{date: int, amount: int, description: ?int, reference: ?int}  $mapping
     *                                                                                      Zero-based column indices.
     */
    public function parse(string $content, array $mapping, string $delimiter = ','): void
    {
        $this->entries = [];
        $lines = str_getcsv_lines($content, $delimiter);

        // Skip header row
        array_shift($lines);

        foreach ($lines as $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            $dateStr = trim($row[$mapping['date']] ?? '');
            $amountStr = trim($row[$mapping['amount']] ?? '');
            $description = isset($mapping['description']) ? trim($row[$mapping['description']] ?? '') : null;
            $reference = isset($mapping['reference']) ? trim($row[$mapping['reference']] ?? '') : null;

            if ($dateStr === '' || $amountStr === '') {
                continue;
            }

            // Normalize amount: handle European format (1.234,56 → 1234.56)
            $amount = str_replace("'", '', $amountStr); // Swiss thousand separator
            $amount = str_replace(' ', '', $amount);
            if (str_contains($amount, ',') && str_contains($amount, '.')) {
                // 1.234,56 format
                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);
            } elseif (str_contains($amount, ',')) {
                $amount = str_replace(',', '.', $amount);
            }

            $numericAmount = (float) $amount;
            $type = $numericAmount >= 0 ? BankTransactionType::Credit : BankTransactionType::Debit;
            $absAmount = (string) abs($numericAmount);

            // Parse date (try common formats)
            $date = $this->parseDate($dateStr);
            if (! $date) {
                continue;
            }

            $this->entries[] = new CamtEntry(
                date: $date,
                amount: $absAmount,
                currency: 'CHF',
                type: $type,
                reference: $reference,
                description: $description,
                iban: null,
                debtorName: null,
                creditorName: null,
                endToEndId: null,
            );
        }
    }

    /** @return CamtEntry[] */
    public function getEntries(): array
    {
        return $this->entries;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Read headers from the first row of a CSV.
     *
     * @return string[]
     */
    public static function readHeaders(string $content, string $delimiter = ','): array
    {
        $lines = str_getcsv_lines($content, $delimiter);

        return $lines[0] ?? [];
    }

    private function parseDate(string $dateStr): ?string
    {
        // Try ISO format first (2025-01-15)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }
        // DD.MM.YYYY (Swiss/German)
        if (preg_match('#^(\d{1,2})[./](\d{1,2})[./](\d{4})$#', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // DD/MM/YYYY
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // MM/DD/YYYY (US)
        // Ambiguous — skip, handled by DD/MM/YYYY above

        return null;
    }
}

/**
 * Parse CSV content into rows, handling quoted fields properly.
 *
 * @return array<int, string[]>
 */
function str_getcsv_lines(string $content, string $delimiter = ','): array
{
    $rows = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}
