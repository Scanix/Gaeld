<?php

namespace Tests\Unit\Migration;

use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Parsers\GenericCsvParser;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GenericCsvParserTest extends TestCase
{
    private GenericCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GenericCsvParser;
    }

    public function test_parser_identifies_platform_and_supported_types(): void
    {
        $this->assertSame('generic_csv', $this->parser->platform()->value);
        $this->assertSame(DataType::cases(), $this->parser->supportedDataTypes());
        $this->assertContains('csv', $this->parser->acceptedExtensions());
    }

    public function test_parse_accounts_csv(): void
    {
        $csv = "code,name,type,description\n1020,Bank,asset,Main bank account\n3000,Revenue,revenue,Sales revenue\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $this->parser->setColumnMapping([
            'code' => 0,
            'name' => 1,
            'type' => 2,
            'description' => 3,
        ]);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(AccountImportRow::class, $rows[0]);
        $this->assertSame('1020', $rows[0]->code);
        $this->assertSame('Bank', $rows[0]->name);
        $this->assertSame('asset', $rows[0]->type);
    }

    public function test_parse_contacts_csv(): void
    {
        $csv = "type,name,email,phone\ncustomer,Acme Corp,acme@example.com,+41791234567\nsupplier,Paper Inc,paper@example.com,\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $this->parser->setColumnMapping([
            'type' => 0,
            'name' => 1,
            'email' => 2,
            'phone' => 3,
        ]);

        $rows = $this->parser->parse($file, DataType::Contacts);

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(ContactImportRow::class, $rows[0]);
        $this->assertSame('customer', $rows[0]->type);
        $this->assertSame('Acme Corp', $rows[0]->name);
        $this->assertSame('acme@example.com', $rows[0]->email);
    }

    public function test_parse_with_semicolon_delimiter(): void
    {
        $csv = "code;name;type\n1020;Bank;asset\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $this->parser->setDelimiter(';');
        $this->parser->setColumnMapping([
            'code' => 0,
            'name' => 1,
            'type' => 2,
        ]);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(1, $rows);
        $this->assertSame('1020', $rows[0]->code);
    }

    public function test_parse_empty_file_returns_empty_collection(): void
    {
        $csv = "code,name,type\n";
        $file = UploadedFile::fake()->createWithContent('empty.csv', $csv);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(0, $rows);
    }

    public function test_parse_skips_blank_lines(): void
    {
        $csv = "code,name,type\n1020,Bank,asset\n\n\n3000,Revenue,revenue\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $this->parser->setColumnMapping([
            'code' => 0,
            'name' => 1,
            'type' => 2,
        ]);

        $rows = $this->parser->parse($file, DataType::Accounts);

        $this->assertCount(2, $rows);
    }

    public function test_extract_headers_returns_first_row(): void
    {
        $csv = "Konto,Name,Typ,Beschreibung\n1020,Bank,asset,main\n";
        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csv);

        $headers = $this->parser->extractHeaders($file);

        $this->assertSame(['Konto', 'Name', 'Typ', 'Beschreibung'], $headers);
    }

    public function test_detect_data_type_returns_null_for_generic(): void
    {
        $file = UploadedFile::fake()->createWithContent('file.csv', "a,b,c\n1,2,3\n");

        $this->assertNull($this->parser->detectDataType($file));
    }
}
