<?php

namespace App\Domains\Migration\Parsers;

use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ExpenseImportRow;
use App\Domains\Migration\DTOs\InvoiceImportRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Parses Bexio CSV/XLS export files.
 *
 * Bexio exports each data type as a separate CSV/XLS file.
 * This parser handles the known column formats from Bexio.
 */
class BexioParser implements PlatformParserInterface
{
    use CsvRowLookup;

    public function platform(): Platform
    {
        return Platform::Bexio;
    }

    public function labelKey(): string
    {
        return 'migration.platforms.bexio.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.platforms.bexio.description';
    }

    public function supportedDataTypes(): array
    {
        return [
            DataType::Accounts,
            DataType::Contacts,
            DataType::Invoices,
            DataType::Expenses,
        ];
    }

    public function acceptedExtensions(): array
    {
        return ['csv', 'xls', 'xlsx'];
    }

    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        $content = $file->get();
        $rows = $this->parseCsv($content);

        if (empty($rows)) {
            return collect();
        }

        return collect($rows)->map(fn (array $row, int $index) => $this->mapRow($row, $index + 1, $dataType))
            ->filter();
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

        if (str_contains($headerString, 'kontonummer') || str_contains($headerString, 'account_no')) {
            return DataType::Accounts;
        }
        if (str_contains($headerString, 'kontaktname') || str_contains($headerString, 'contact_name')) {
            return DataType::Contacts;
        }
        if (str_contains($headerString, 'rechnungsnummer') || str_contains($headerString, 'invoice_no')) {
            return DataType::Invoices;
        }
        if (str_contains($headerString, 'ausgabe') || str_contains($headerString, 'expense')) {
            return DataType::Expenses;
        }

        return null;
    }

    private function mapRow(array $row, int $sourceRow, DataType $dataType): ?ImportRowInterface
    {
        return match ($dataType) {
            DataType::Accounts => $this->mapAccount($row, $sourceRow),
            DataType::Contacts => $this->mapContact($row, $sourceRow),
            DataType::Invoices => $this->mapInvoice($row, $sourceRow),
            DataType::Expenses => $this->mapExpense($row, $sourceRow),
            default => null,
        };
    }

    private function mapAccount(array $row, int $sourceRow): AccountImportRow
    {
        $code = $this->findValue($row, ['account_no', 'kontonummer', 'no_compte', 'code']);
        $name = $this->findValue($row, ['account_name', 'kontoname', 'nom_compte', 'name']);
        $type = $this->mapAccountType($this->findValue($row, ['account_type', 'kontotyp', 'type_compte', 'type']));

        $importRow = new AccountImportRow(
            sourceRow: $sourceRow,
            code: $code ?? '',
            name: $name ?? '',
            type: $type,
        );

        if (! $code || ! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: code or name');
        }

        return $importRow;
    }

    private function mapContact(array $row, int $sourceRow): ContactImportRow
    {
        $name = $this->findValue($row, ['contact_name', 'kontaktname', 'nom_contact', 'name', 'firma', 'company']);
        $type = $this->findValue($row, ['contact_type', 'typ', 'type']);

        $contactType = match (strtolower($type ?? '')) {
            'kunde', 'customer', 'client' => 'customer',
            'lieferant', 'supplier', 'fournisseur' => 'supplier',
            default => 'customer',
        };

        $importRow = new ContactImportRow(
            sourceRow: $sourceRow,
            type: $contactType,
            name: $name ?? '',
            email: $this->findValue($row, ['email', 'e_mail', 'mail']),
            phone: $this->findValue($row, ['phone', 'telefon', 'telephone']),
            address: $this->findValue($row, ['address', 'adresse', 'strasse']),
            zip: $this->findValue($row, ['zip', 'plz', 'code_postal']),
            city: $this->findValue($row, ['city', 'ort', 'ville']),
            country: $this->findValue($row, ['country', 'land', 'pays']),
        );

        if (! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: name');
        }

        return $importRow;
    }

    private function mapInvoice(array $row, int $sourceRow): InvoiceImportRow
    {
        $number = $this->findValue($row, ['invoice_no', 'rechnungsnummer', 'no_facture', 'number']);
        $date = $this->findValue($row, ['invoice_date', 'rechnungsdatum', 'date_facture', 'date']);
        $total = $this->findValue($row, ['total', 'total_amount', 'betrag', 'montant']);
        $status = $this->mapInvoiceStatus($this->findValue($row, ['status', 'zustand', 'etat']));
        $customer = $this->findValue($row, ['customer', 'kunde', 'client', 'contact_name', 'kontaktname']);

        $importRow = new InvoiceImportRow(
            sourceRow: $sourceRow,
            number: $number ?? '',
            date: $date ?? '',
            dueDate: $this->findValue($row, ['due_date', 'faelligkeitsdatum', 'date_echeance']),
            status: $status,
            customerName: $customer ?? '',
            totalAmount: $total,
            reference: $this->findValue($row, ['reference', 'referenz', 'ref']),
        );

        if (! $number || ! $date) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: number or date');
        }

        return $importRow;
    }

    private function mapExpense(array $row, int $sourceRow): ExpenseImportRow
    {
        $date = $this->findValue($row, ['date', 'datum', 'date_charge']);
        $amount = $this->findValue($row, ['amount', 'betrag', 'montant', 'total']);

        $importRow = new ExpenseImportRow(
            sourceRow: $sourceRow,
            date: $date ?? '',
            amount: $amount ?? '0',
            description: $this->findValue($row, ['description', 'beschreibung', 'designation']),
            category: $this->findValue($row, ['category', 'kategorie', 'categorie']),
            supplierName: $this->findValue($row, ['supplier', 'lieferant', 'fournisseur', 'vendor']),
            accountCode: $this->findValue($row, ['account', 'konto', 'compte']),
            reference: $this->findValue($row, ['reference', 'referenz', 'ref']),
        );

        if (! $date || ! $amount) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: date or amount');
        }

        return $importRow;
    }

    private function mapAccountType(?string $type): string
    {
        if (! $type) {
            return 'asset';
        }

        return match (strtolower($type)) {
            'aktiv', 'asset', 'actif' => 'asset',
            'passiv', 'liability', 'passif' => 'liability',
            'aufwand', 'expense', 'charge' => 'expense',
            'ertrag', 'revenue', 'income', 'produit' => 'revenue',
            'eigenkapital', 'equity', 'fonds_propres' => 'equity',
            default => 'asset',
        };
    }

    private function mapInvoiceStatus(?string $status): string
    {
        if (! $status) {
            return 'draft';
        }

        return match (strtolower($status)) {
            'bezahlt', 'paid', 'payé' => 'paid',
            'offen', 'open', 'sent', 'envoyé', 'envoyee' => 'sent',
            'überfällig', 'overdue', 'en_retard' => 'overdue',
            'storniert', 'cancelled', 'annulé', 'annulee' => 'cancelled',
            default => 'draft',
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

        // Handle Bexio SEP= header
        $firstLine = $lines[0] ?? '';
        $delimiter = ',';
        if (str_starts_with(strtoupper(trim($firstLine)), 'SEP=')) {
            $delimiter = trim(substr(trim($firstLine), 4));
            array_shift($lines);
        }

        if (empty($lines)) {
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
