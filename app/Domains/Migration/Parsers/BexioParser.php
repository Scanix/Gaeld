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
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Parses Bexio CSV/XLSX export files.
 *
 * Bexio exports each data type as a separate file (addresses, invoices,
 * bills, expenses). This parser handles all known English column formats
 * produced by the Bexio web export feature.
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
        return ['csv', 'xlsx'];
    }

    /**
     * @return Collection<int, ImportRowInterface>
     */
    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'xlsx' => $this->parseXlsx($file),
            default => $this->parseCsvToAssoc((string) $file->get()),
        };

        if (empty($rows)) {
            return collect();
        }

        $result = collect();
        foreach ($rows as $index => $row) {
            $importRow = $this->mapRow($row, $index + 1, $dataType);
            if ($importRow !== null) {
                $result->push($importRow);
            }
        }

        return $result;
    }

    public function detectDataType(UploadedFile $file): ?DataType
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            $rows = $this->parseXlsx($file);
        } else {
            $rows = $this->parseCsvToAssoc((string) $file->get());
        }

        if (empty($rows)) {
            return null;
        }

        $headers = array_keys($rows[0]);
        $headerString = implode(',', array_map('strtolower', $headers));

        // Bexio XLSX English headers (addresses export)
        if (str_contains($headerString, 'contact no.') || str_contains($headerString, 'first name')) {
            return DataType::Contacts;
        }
        // Bexio invoices export: has 'no.' and 'gross amount'
        if (str_contains($headerString, 'gross amount') || str_contains($headerString, 'net amount')) {
            return DataType::Invoices;
        }
        // Bexio bills export: has 'vendor' and 'accounting account'
        if (str_contains($headerString, 'vendor') || str_contains($headerString, 'booking date')) {
            return DataType::Expenses;
        }
        // Bexio expenses export: 'paid on' and 'title / booking text'
        if (str_contains($headerString, 'paid on') || str_contains($headerString, 'booking text')) {
            return DataType::Expenses;
        }
        // Fallback: legacy CSV DE/FR headers
        if (str_contains($headerString, 'kontonummer') || str_contains($headerString, 'account_no')) {
            return DataType::Accounts;
        }
        if (str_contains($headerString, 'kontaktname') || str_contains($headerString, 'contact_name')) {
            return DataType::Contacts;
        }
        if (str_contains($headerString, 'rechnungsnummer') || str_contains($headerString, 'invoice_no')) {
            return DataType::Invoices;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseXlsx(UploadedFile $file): array
    {
        $reader = new XlsxReader;
        $reader->open($file->getPathname());

        $rows = [];
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cells = $row->toArray();

                if ($rowIndex === 1) {
                    $headers = array_map(
                        function (mixed $v): string {
                            if ($v instanceof \DateTimeInterface) {
                                return $v->format('Y-m-d');
                            }

                            return is_scalar($v) || $v === null ? (string) ($v ?? '') : '';
                        },
                        $cells
                    );

                    continue;
                }

                if (empty(array_filter($cells, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                // Pad to header count in case trailing cells are missing
                while (count($cells) < count($headers)) {
                    $cells[] = null;
                }

                $rows[] = array_combine($headers, array_slice($cells, 0, count($headers)));
            }
            break; // Only first sheet
        }

        $reader->close();

        return $rows;
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
        $code = $this->findValue($row, ['account_no', 'kontonummer', 'no_compte', 'code', 'No.']);
        $name = $this->findValue($row, ['account_name', 'kontoname', 'nom_compte', 'name', 'Name']);
        $type = $this->mapAccountType($this->findValue($row, ['account_type', 'kontotyp', 'type_compte', 'type', 'Type']));

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
        // Bexio XLSX addresses export columns
        $firstName = $this->findValue($row, ['First name', 'first_name', 'Vorname', 'prenom']);
        $lastName = $this->findValue($row, ['Last name', 'last_name', 'Nachname', 'nom']);
        $company = $this->findValue($row, ['Company name', 'company_name', 'Firmenname', 'nom_entreprise', 'firma']);
        $typeLabel = $this->findValue($row, ['Contact type description', 'contact_type', 'typ', 'Type', 'type']);

        // Build a displayable name
        $name = trim(implode(' ', array_filter([$firstName, $lastName])));
        if ($name === '') {
            $name = $company ?? '';
        }

        $contactType = match (strtolower($typeLabel ?? '')) {
            'person', 'private', 'individual', 'person (private)' => 'customer',
            'company', 'unternehmen', 'entreprise' => 'customer',
            'supplier', 'lieferant', 'fournisseur' => 'supplier',
            'kunde', 'customer', 'client' => 'customer',
            default => 'customer',
        };

        // Address: Bexio XLSX has a combined "Address" field
        $address = $this->findValue($row, ['Address', 'Street', 'Strasse', 'adresse']);
        $street = $this->findValue($row, ['Street', 'Strasse']);
        $houseNo = $this->findValue($row, ['House no.', 'house_no', 'Hausnummer']);
        if ($address === null && $street !== null) {
            $address = trim($street.' '.($houseNo ?? ''));
        }

        $importRow = new ContactImportRow(
            sourceRow: $sourceRow,
            type: $contactType,
            name: $name,
            email: $this->findValue($row, ['Email', 'email', 'E-Mail', 'e_mail', 'mail']),
            phone: $this->findValue($row, ['Phone', 'phone', 'Telefon', 'telephone']),
            address: $address,
            zip: (string) ($this->findValue($row, ['Postcode', 'postcode', 'PLZ', 'code_postal', 'zip']) ?? ''),
            city: $this->findValue($row, ['City', 'city', 'Ort', 'ville']),
            country: $this->findValue($row, ['Country', 'country', 'Land', 'pays']),
        );

        if (! $name) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: name');
        }

        return $importRow;
    }

    private function mapInvoice(array $row, int $sourceRow): InvoiceImportRow
    {
        // Bexio XLSX invoices export columns (English)
        $number = $this->findValue($row, ['No.', 'invoice_no', 'rechnungsnummer', 'no_facture', 'number']);
        $title = $this->findValue($row, ['Title', 'title', 'Titel', 'titre']);
        $date = $this->normalizeDate($this->findValue($row, ['Date', 'invoice_date', 'rechnungsdatum', 'date_facture']));
        $dueDate = $this->normalizeDate($this->findValue($row, ['Deadline', 'due_date', 'faelligkeitsdatum', 'date_echeance']));
        $total = $this->findValue($row, ['Gross amount', 'gross_amount', 'Total', 'total', 'total_amount', 'betrag', 'montant']);
        $net = $this->findValue($row, ['Net amount', 'net_amount']);
        $status = $this->mapInvoiceStatus($this->findValue($row, ['Status', 'status', 'zustand', 'etat']));
        $customer = $this->findValue($row, ['Contact', 'contact', 'customer', 'kunde', 'client', 'contact_name']);
        $currency = $this->findValue($row, ['Currency', 'currency', 'Währung', 'devise']) ?? 'CHF';
        $reference = $this->findValue($row, ['QR reference', 'Reference', 'reference', 'referenz', 'ref']);

        $importRow = new InvoiceImportRow(
            sourceRow: $sourceRow,
            number: $number ?? '',
            date: $date ?? '',
            dueDate: $dueDate,
            status: $status,
            customerName: $customer ?? ($title ?? ''),
            totalAmount: $total !== null ? (string) $total : null,
            reference: $reference,
        );

        if (! $number || ! $date) {
            $importRow->markInvalid();
            $importRow->addWarning('Missing required field: number or date');
        }

        return $importRow;
    }

    private function mapExpense(array $row, int $sourceRow): ExpenseImportRow
    {
        // Handles both Bexio "bills" and "expenses" XLSX exports
        $date = $this->normalizeDate($this->findValue($row, ['Date', 'Booking date', 'date', 'booking_date', 'datum', 'date_charge']));
        $amount = $this->findValue($row, ['Gross', 'gross', 'Net', 'net', 'amount', 'betrag', 'montant', 'total']);
        $description = $this->findValue($row, ['Title / Booking text', 'Title', 'title', 'description', 'beschreibung', 'designation']);
        $vendor = $this->findValue($row, ['Vendor', 'vendor', 'Contact', 'contact', 'supplier', 'lieferant', 'fournisseur']);
        $accountCode = $this->findValue($row, ['Accounting account', 'accounting_account', 'account', 'konto', 'compte']);
        $reference = $this->findValue($row, ['Reference', 'reference', 'referenz', 'ref']);

        $importRow = new ExpenseImportRow(
            sourceRow: $sourceRow,
            date: $date ?? '',
            amount: $amount !== null ? (string) $amount : '0',
            description: $description,
            category: null,
            supplierName: $vendor,
            accountCode: $accountCode !== null ? (string) $accountCode : null,
            reference: $reference,
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
            'bezahlt', 'paid', 'payé', 'paye' => 'paid',
            'offen', 'open', 'sent', 'envoyé', 'envoye' => 'sent',
            'überfällig', 'uberfällig', 'overdue', 'en_retard' => 'overdue',
            'storniert', 'cancelled', 'annulé', 'annule' => 'cancelled',
            default => 'draft',
        };
    }

    /**
     * Normalize various date formats (DD.MM.YYYY, DateTime objects, ISO) to Y-m-d.
     */
    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $str = (string) $value;

        // DD.MM.YYYY (Bexio CSV)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Already ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str)) {
            return substr($str, 0, 10);
        }

        return $str;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCsvToAssoc(string $content): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));

        if (count($lines) < 2) {
            return [];
        }

        // Handle Bexio SEP= header
        $firstLine = reset($lines);
        $delimiter = ',';
        if (str_starts_with(strtoupper(trim($firstLine)), 'SEP=')) {
            $delimiter = trim(substr(trim($firstLine), 4));
            array_shift($lines);
        }

        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines), $delimiter);
        $headers = array_map(fn (?string $v): string => trim((string) $v), $headers);

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
