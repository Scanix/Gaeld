<?php

namespace App\Domains\Migration\Parsers;

use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\JournalEntryImportRow;
use App\Domains\Migration\DTOs\OpeningBalanceRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Parses Banana Comptabilité TXT/CSV export files.
 *
 * Banana exports accounts + journal (Écritures) as tab-separated or
 * semicolon-separated files.
 */
class BananaParser implements PlatformParserInterface
{
    public function platform(): Platform
    {
        return Platform::Banana;
    }

    public function labelKey(): string
    {
        return 'migration.platforms.banana.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.platforms.banana.description';
    }

    public function supportedDataTypes(): array
    {
        return [
            DataType::Accounts,
            DataType::JournalEntries,
            DataType::OpeningBalances,
        ];
    }

    public function acceptedExtensions(): array
    {
        return ['csv', 'txt', 'tsv'];
    }

    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        $content = $file->get();
        $delimiter = $this->detectDelimiter($content);
        $rows = $this->parseCsv($content, $delimiter);

        if (empty($rows)) {
            return collect();
        }

        return collect($rows)->map(fn (array $row, int $index) => $this->mapRow($row, $index + 1, $dataType))
            ->filter();
    }

    public function detectDataType(UploadedFile $file): ?DataType
    {
        $content = $file->get();
        $delimiter = $this->detectDelimiter($content);
        $rows = $this->parseCsv($content, $delimiter);

        if (empty($rows)) {
            return null;
        }

        $headers = array_keys($rows[0]);
        $headerString = implode(',', array_map('strtolower', $headers));

        if (str_contains($headerString, 'group') || str_contains($headerString, 'bclass')) {
            return DataType::Accounts;
        }
        if (str_contains($headerString, 'doc') && str_contains($headerString, 'accountdebit')) {
            return DataType::JournalEntries;
        }

        return null;
    }

    private function mapRow(array $row, int $sourceRow, DataType $dataType): ?ImportRowInterface
    {
        return match ($dataType) {
            DataType::Accounts => $this->mapAccount($row, $sourceRow),
            DataType::JournalEntries => $this->mapJournalEntry($row, $sourceRow),
            DataType::OpeningBalances => $this->mapOpeningBalance($row, $sourceRow),
            default => null,
        };
    }

    private function mapAccount(array $row, int $sourceRow): AccountImportRow
    {
        $code = $this->findValue($row, ['account', 'konto', 'compte', 'conto']);
        $name = $this->findValue($row, ['description', 'beschreibung', 'libellé', 'descrizione']);
        $bclass = $this->findValue($row, ['bclass', 'classe']);

        $type = match ($bclass) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'expense',
            '4' => 'revenue',
            default => 'asset',
        };

        $importRow = new AccountImportRow(
            sourceRow: $sourceRow,
            code: $code ?? '',
            name: $name ?? '',
            type: $type,
        );

        if (! $code || ! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: account or description');
        }

        return $importRow;
    }

    private function mapJournalEntry(array $row, int $sourceRow): JournalEntryImportRow
    {
        $date = $this->findValue($row, ['date', 'datum', 'data']);
        $debitAccount = $this->findValue($row, ['accountdebit', 'kontosoll', 'comptedébit']);
        $creditAccount = $this->findValue($row, ['accountcredit', 'kontohaben', 'comptecrédit']);
        $amount = $this->findValue($row, ['amount', 'betrag', 'montant', 'importo']);
        $description = $this->findValue($row, ['description', 'beschreibung', 'libellé', 'descrizione']);
        $doc = $this->findValue($row, ['doc', 'beleg', 'pièce']);

        $lines = [];
        if ($debitAccount && $amount) {
            $lines[] = [
                'account_code' => $debitAccount,
                'debit' => $amount,
                'credit' => null,
                'description' => $description,
            ];
        }
        if ($creditAccount && $amount) {
            $lines[] = [
                'account_code' => $creditAccount,
                'debit' => null,
                'credit' => $amount,
                'description' => $description,
            ];
        }

        $importRow = new JournalEntryImportRow(
            sourceRow: $sourceRow,
            date: $date ?? '',
            reference: $doc,
            description: $description,
            lines: $lines,
        );

        if (! $date || empty($lines)) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: date, debit account, or credit account');
        }

        return $importRow;
    }

    private function mapOpeningBalance(array $row, int $sourceRow): OpeningBalanceRow
    {
        $code = $this->findValue($row, ['account', 'konto', 'compte', 'conto']);
        $name = $this->findValue($row, ['description', 'beschreibung', 'libellé', 'descrizione']);
        $opening = $this->findValue($row, ['opening', 'eröffnung', 'ouverture', 'apertura']);

        $debit = null;
        $credit = null;
        if ($opening !== null) {
            $amount = (float) str_replace(["'", ' '], '', $opening);
            if ($amount > 0) {
                $debit = (string) $amount;
            } elseif ($amount < 0) {
                $credit = (string) abs($amount);
            }
        }

        $importRow = new OpeningBalanceRow(
            sourceRow: $sourceRow,
            accountCode: $code ?? '',
            accountName: $name,
            debit: $debit,
            credit: $credit,
        );

        if (! $code) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: account code');
        }

        return $importRow;
    }

    /**
     * @param  string[]  $keys
     */
    private function findValue(array $row, array $keys): ?string
    {
        $lowered = array_change_key_case($row, CASE_LOWER);

        foreach ($keys as $key) {
            $key = strtolower($key);
            if (isset($lowered[$key]) && $lowered[$key] !== '') {
                return trim($lowered[$key]);
            }
        }

        return null;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n");

        if ($firstLine === false) {
            return ';';
        }

        $tabCount = substr_count($firstLine, "\t");
        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        if ($tabCount >= $semicolonCount && $tabCount >= $commaCount) {
            return "\t";
        }
        if ($semicolonCount >= $commaCount) {
            return ';';
        }

        return ',';
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    private function parseCsv(string $content, string $delimiter): ?array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));

        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv(array_shift($lines), $delimiter);
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            if (count($values) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }
}
