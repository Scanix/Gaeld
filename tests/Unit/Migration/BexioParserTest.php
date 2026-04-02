<?php

namespace Tests\Unit\Migration;

use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ExpenseImportRow;
use App\Domains\Migration\DTOs\InvoiceImportRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Parsers\BexioParser;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BexioParserTest extends TestCase
{
    private BexioParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BexioParser;
    }

    public function test_parser_identifies_platform(): void
    {
        $this->assertSame('bexio', $this->parser->platform()->value);
    }

    public function test_supported_data_types(): void
    {
        $types = $this->parser->supportedDataTypes();

        $this->assertContains(DataType::Accounts, $types);
        $this->assertContains(DataType::Contacts, $types);
        $this->assertContains(DataType::Invoices, $types);
        $this->assertContains(DataType::Expenses, $types);
        $this->assertNotContains(DataType::JournalEntries, $types);
    }

    public function test_parse_accounts_with_german_headers(): void
    {
        $csv = "Kontonummer,Kontoname,Kontotyp\n1020,Bank,Aktiv\n3000,Umsatz,Ertrag\n";
        $file = UploadedFile::fake()->createWithContent('kontenplan.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(AccountImportRow::class, $rows[0]);
        $this->assertSame('1020', $rows[0]->code);
        $this->assertSame('Bank', $rows[0]->name);
        $this->assertSame('asset', $rows[0]->type);
        $this->assertSame('revenue', $rows[1]->type);
    }

    public function test_parse_accounts_with_english_headers(): void
    {
        $csv = "account_no,account_name,account_type\n1020,Bank,asset\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(1, $rows);
        $this->assertSame('1020', $rows[0]->code);
        $this->assertSame('Bank', $rows[0]->name);
    }

    public function test_parse_contacts_german_headers(): void
    {
        $csv = "Kontaktname,Typ,Email,Telefon,Adresse,PLZ,Ort,Land\nAcme GmbH,Kunde,acme@test.ch,+41791234567,Hauptstrasse 1,3000,Bern,CH\n";
        $file = UploadedFile::fake()->createWithContent('kontakte.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Contacts);

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(ContactImportRow::class, $rows[0]);
        $this->assertSame('customer', $rows[0]->type);
        $this->assertSame('Acme GmbH', $rows[0]->name);
        $this->assertSame('acme@test.ch', $rows[0]->email);
        $this->assertSame('Bern', $rows[0]->city);
    }

    public function test_parse_contacts_marks_invalid_when_name_missing(): void
    {
        $csv = "Kontaktname,Typ,Email\n,Kunde,test@test.ch\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Contacts);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->isValid());
    }

    public function test_parse_invoices(): void
    {
        $csv = "Rechnungsnummer,Rechnungsdatum,Kunde,Total,Status\nINV-001,2026-01-15,Acme GmbH,1500.00,Bezahlt\n";
        $file = UploadedFile::fake()->createWithContent('rechnungen.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Invoices);

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(InvoiceImportRow::class, $rows[0]);
        $this->assertSame('INV-001', $rows[0]->number);
        $this->assertSame('paid', $rows[0]->status);
        $this->assertSame('Acme GmbH', $rows[0]->customerName);
    }

    public function test_parse_expenses(): void
    {
        $csv = "Datum,Betrag,Beschreibung,Lieferant,Konto\n2026-01-15,250.00,Office supplies,Paper Inc,6500\n";
        $file = UploadedFile::fake()->createWithContent('ausgaben.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Expenses);

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(ExpenseImportRow::class, $rows[0]);
        $this->assertSame('2026-01-15', $rows[0]->date);
        $this->assertSame('250.00', $rows[0]->amount);
        $this->assertSame('Paper Inc', $rows[0]->supplierName);
    }

    public function test_detect_data_type_from_headers(): void
    {
        $accountsCsv = "Kontonummer,Kontoname,Kontotyp\n1020,Bank,Aktiv\n";
        $contactsCsv = "Kontaktname,Email\nAcme,acme@test.ch\n";
        $invoicesCsv = "Rechnungsnummer,Datum\nINV-001,2026-01-15\n";

        $this->assertSame(DataType::Accounts, $this->parser->detectDataType(
            UploadedFile::fake()->createWithContent('f.csv', $accountsCsv)
        ));
        $this->assertSame(DataType::Contacts, $this->parser->detectDataType(
            UploadedFile::fake()->createWithContent('f.csv', $contactsCsv)
        ));
        $this->assertSame(DataType::Invoices, $this->parser->detectDataType(
            UploadedFile::fake()->createWithContent('f.csv', $invoicesCsv)
        ));
    }

    public function test_parse_empty_file_returns_empty_collection(): void
    {
        $csv = "Kontonummer,Kontoname\n";
        $file = UploadedFile::fake()->createWithContent('empty.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertTrue($rows->isEmpty());
    }

    public function test_handles_bexio_sep_header(): void
    {
        $csv = "SEP=;\nKontonummer;Kontoname;Kontotyp\n1020;Bank;Aktiv\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(1, $rows);
        $this->assertSame('1020', $rows[0]->code);
    }

    public function test_unsupported_data_type_returns_null_rows(): void
    {
        $csv = "header1,header2\nval1,val2\n";
        $file = UploadedFile::fake()->createWithContent('file.csv', $csv);

        $rows = $this->parser->parse($file, DataType::JournalEntries);

        // Non-supported type returns rows via mapRow → null, then filtered
        $this->assertTrue($rows->isEmpty());
    }
}
