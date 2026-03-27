<?php

namespace Tests\Unit\Services;

use App\Domains\Expenses\Services\TesseractOcrService;
use Tests\TestCase;

class TesseractOcrServiceTest extends TestCase
{
    private TesseractOcrService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TesseractOcrService;
    }

    public function test_extracts_amount_with_chf_prefix(): void
    {
        $text = "Migros\n12.03.2025\nMilch 2.50\nBrot 3.80\nTotal CHF 6.30";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(6.30, $amount);
    }

    public function test_extracts_amount_with_chf_suffix(): void
    {
        $text = "Coop\nTotal: 45.90 CHF";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(45.90, $amount);
    }

    public function test_extracts_amount_with_total_keyword(): void
    {
        $text = "Restaurant zum Goldenen Hirsch\nKaffee 4.50\nKuchen 6.00\nTotal: 10.50";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(10.50, $amount);
    }

    public function test_extracts_amount_with_comma_decimal(): void
    {
        $text = "Elektronik AG\nTotal CHF 1299,00";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(1299.00, $amount);
    }

    public function test_extracts_amount_fallback_to_largest_number(): void
    {
        $text = "Some shop\n2.50\n3.80\n15.90";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(15.90, $amount);
    }

    public function test_returns_null_for_no_amount(): void
    {
        $amount = $this->service->extractAmount('Hello world no numbers');
        $this->assertNull($amount);
    }

    public function test_extracts_date_dd_mm_yyyy_dots(): void
    {
        $text = "Receipt\n15.03.2025\nTotal CHF 10.00";

        $date = $this->service->extractDate($text);
        $this->assertEquals('2025-03-15', $date);
    }

    public function test_extracts_date_dd_mm_yyyy_slashes(): void
    {
        $text = "Receipt\n15/03/2025\nTotal CHF 10.00";

        $date = $this->service->extractDate($text);
        $this->assertEquals('2025-03-15', $date);
    }

    public function test_extracts_date_yyyy_mm_dd(): void
    {
        $text = "Receipt\n2025-03-15\nTotal CHF 10.00";

        $date = $this->service->extractDate($text);
        $this->assertEquals('2025-03-15', $date);
    }

    public function test_returns_null_for_no_date(): void
    {
        $date = $this->service->extractDate('Just some text without dates');
        $this->assertNull($date);
    }

    public function test_extracts_vendor_first_line(): void
    {
        $text = "Migros Bahnhofstrasse\n15.03.2025\nTotal CHF 10.00";

        $vendor = $this->service->extractVendor($text);
        $this->assertEquals('Migros Bahnhofstrasse', $vendor);
    }

    public function test_skips_numeric_lines_for_vendor(): void
    {
        $text = "12345\n67890\nCoop Pronto\nTotal CHF 5.00";

        $vendor = $this->service->extractVendor($text);
        $this->assertEquals('Coop Pronto', $vendor);
    }

    public function test_skips_date_lines_for_vendor(): void
    {
        $text = "15.03.2025\nElektro Müller AG\nTotal CHF 99.00";

        $vendor = $this->service->extractVendor($text);
        $this->assertEquals('Elektro Müller AG', $vendor);
    }

    public function test_returns_null_for_empty_text(): void
    {
        $vendor = $this->service->extractVendor('');
        $this->assertNull($vendor);
    }

    public function test_extracts_eur_amount(): void
    {
        $text = "Amazon\nTotal EUR 29.99";

        $amount = $this->service->extractAmount($text);
        $this->assertEquals(29.99, $amount);
    }

    public function test_full_receipt_parsing(): void
    {
        $text = <<<'TEXT'
        Migros Zürich HB
        Bahnhofstrasse 42
        8001 Zürich

        15.03.2025  12:34

        Milch        CHF  2.50
        Brot         CHF  3.80
        Käse         CHF  6.40

        Total CHF 12.70
        TEXT;

        $amount = $this->service->extractAmount($text);
        $date = $this->service->extractDate($text);
        $vendor = $this->service->extractVendor($text);

        $this->assertEquals(12.70, $amount);
        $this->assertEquals('2025-03-15', $date);
        $this->assertEquals('Migros Zürich HB', $vendor);
    }
}
