<?php

namespace App\Domains\Migration\Parsers;

use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\FixedAssetImportRow;
use App\Domains\Migration\DTOs\JournalEntryImportRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use Illuminate\Http\UploadedFile;

/**
 * Parses Abacus CSV/XML export files.
 *
 * Supports chart of accounts, contacts, journal entries, and fixed assets.
 */
class AbacusParser implements PlatformParserInterface
{
    use CsvRowLookup;

    public function platform(): Platform
    {
        return Platform::Abacus;
    }

    public function labelKey(): string
    {
        return 'migration.platforms.abacus.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.platforms.abacus.description';
    }

    public function supportedDataTypes(): array
    {
        return [
            DataType::Accounts,
            DataType::Contacts,
            DataType::JournalEntries,
            DataType::FixedAssets,
        ];
    }

    public function acceptedExtensions(): array
    {
        return ['csv', 'xml', 'txt'];
    }

    public function detectDataType(UploadedFile $file): ?DataType
    {
        $content = $file->get();
        $rows = $this->parseCsv($content);

        if (empty($rows)) {
            return null;
        }

        $headers = array_keys($rows[0]);
        $headerString = implode(',', array_map('strtolower', $headers));

        if (str_contains($headerString, 'kontonr') || str_contains($headerString, 'kontotyp')) {
            return DataType::Accounts;
        }
        if (str_contains($headerString, 'adressnr') || str_contains($headerString, 'adresstyp')) {
            return DataType::Contacts;
        }
        if (str_contains($headerString, 'buchungsnr') || str_contains($headerString, 'sollkonto')) {
            return DataType::JournalEntries;
        }
        if (str_contains($headerString, 'anlagenr') || str_contains($headerString, 'anschaffung')) {
            return DataType::FixedAssets;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapRow(array $row, int $sourceRow, DataType $dataType): ?ImportRowInterface
    {
        return match ($dataType) {
            DataType::Accounts => $this->mapAccount($row, $sourceRow),
            DataType::Contacts => $this->mapContact($row, $sourceRow),
            DataType::JournalEntries => $this->mapJournalEntry($row, $sourceRow),
            DataType::FixedAssets => $this->mapFixedAsset($row, $sourceRow),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapAccount(array $row, int $sourceRow): AccountImportRow
    {
        $code = $this->findValue($row, ['kontonr', 'account_no', 'konto', 'code']);
        $name = $this->findValue($row, ['bezeichnung', 'description', 'name', 'kontoname']);
        $type = $this->mapAccountType($this->findValue($row, ['kontotyp', 'type', 'klasse']));

        $importRow = new AccountImportRow(
            sourceRow: $sourceRow,
            code: $code ?? '',
            name: $name ?? '',
            type: $type,
        );

        if (! $code || ! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: account number or name');
        }

        return $importRow;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapContact(array $row, int $sourceRow): ContactImportRow
    {
        $name = $this->findValue($row, ['name', 'firma', 'bezeichnung', 'nachname']);
        $firstName = $this->findValue($row, ['vorname', 'first_name']);

        if ($firstName && $name) {
            $name = "{$name} {$firstName}";
        }

        $type = match (strtolower($this->findValue($row, ['adresstyp', 'type']) ?? '')) {
            'debitor', 'kunde' => 'customer',
            'kreditor', 'lieferant' => 'supplier',
            default => 'customer',
        };

        $importRow = new ContactImportRow(
            sourceRow: $sourceRow,
            type: $type,
            name: $name ?? '',
            email: $this->findValue($row, ['email', 'e_mail']),
            phone: $this->findValue($row, ['telefon', 'phone', 'tel']),
            address: $this->findValue($row, ['strasse', 'address', 'adresse']),
            zip: $this->findValue($row, ['plz', 'zip', 'postleitzahl']),
            city: $this->findValue($row, ['ort', 'city', 'stadt']),
            country: $this->findValue($row, ['land', 'country']),
        );

        if (! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: name');
        }

        return $importRow;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapJournalEntry(array $row, int $sourceRow): JournalEntryImportRow
    {
        $date = $this->findValue($row, ['datum', 'date', 'buchungsdatum']);
        $debitAccount = $this->findValue($row, ['sollkonto', 'debit_account', 'soll']);
        $creditAccount = $this->findValue($row, ['habenkonto', 'credit_account', 'haben']);
        $amount = $this->findValue($row, ['betrag', 'amount', 'buchungsbetrag']);
        $description = $this->findValue($row, ['text', 'buchungstext', 'beschreibung', 'description']);
        $ref = $this->findValue($row, ['belegnr', 'buchungsnr', 'reference']);

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
            reference: $ref,
            description: $description,
            lines: $lines,
        );

        if (! $date || empty($lines)) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: date or account entries');
        }

        return $importRow;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapFixedAsset(array $row, int $sourceRow): FixedAssetImportRow
    {
        $name = $this->findValue($row, ['bezeichnung', 'name', 'description', 'anlagename']);
        $acquisitionDate = $this->findValue($row, ['anschaffungsdatum', 'acquisition_date', 'kaufdatum']);
        $acquisitionCost = $this->findValue($row, ['anschaffungswert', 'acquisition_cost', 'kaufpreis']);
        $depreciation = $this->findValue($row, ['abschreibungsmethode', 'depreciation_method', 'methode']);
        $rate = $this->findValue($row, ['abschreibungssatz', 'depreciation_rate', 'satz']);
        $accumulated = $this->findValue($row, ['kumulierte_abschreibung', 'accumulated_depreciation']);

        $importRow = new FixedAssetImportRow(
            sourceRow: $sourceRow,
            name: $name ?? '',
            acquisitionDate: $acquisitionDate ?? '',
            acquisitionCost: $acquisitionCost ?? '0',
            depreciationMethod: match (strtolower($depreciation ?? '')) {
                'degressiv', 'declining', 'declining_balance' => 'declining_balance',
                default => 'linear',
            },
            depreciationRate: $rate ? (float) $rate : null,
            accumulatedDepreciation: $accumulated,
            accountCode: $this->findValue($row, ['anlagekonto', 'asset_account']),
            depreciationAccountCode: $this->findValue($row, ['abschreibungskonto', 'depreciation_account']),
            description: $this->findValue($row, ['bemerkung', 'notes', 'notiz']),
        );

        if (! $name || ! $acquisitionDate || ! $acquisitionCost) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: name, acquisition date, or cost');
        }

        return $importRow;
    }

    private function mapAccountType(?string $type): string
    {
        if (! $type) {
            return 'asset';
        }

        return match (strtolower($type)) {
            'a', 'aktiv', 'asset', '1' => 'asset',
            'p', 'passiv', 'liability', '2' => 'liability',
            'e', 'aufwand', 'expense', '3' => 'expense',
            'r', 'ertrag', 'revenue', '4' => 'revenue',
            'k', 'eigenkapital', 'equity' => 'equity',
            default => 'asset',
        };
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    private function parseCsv(string $content): ?array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));

        if (count($lines) < 2) {
            return null;
        }

        // Abacus typically uses semicolons
        $firstLine = $lines[0] ?? '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

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
