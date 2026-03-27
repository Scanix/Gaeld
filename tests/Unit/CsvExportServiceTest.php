<?php

namespace Tests\Unit;

use App\Support\CsvExportService;
use PHPUnit\Framework\TestCase;

class CsvExportServiceTest extends TestCase
{
    public function test_export_returns_streamed_response(): void
    {
        $service = new CsvExportService;

        $response = $service->export(
            ['Name', 'Amount', 'Date'],
            [
                ['Invoice 001', '1500.00', '2025-01-15'],
                ['Invoice 002', '2300.50', '2025-02-20'],
            ],
            'test-export.csv',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('test-export.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_export_content_has_utf8_bom_and_headers(): void
    {
        $service = new CsvExportService;

        $response = $service->export(
            ['Code', 'Nom'],
            [['1000', 'Caisse'], ['1020', 'Banque']],
            'accounts.csv',
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Check UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Check content after BOM
        $lines = explode("\n", substr($content, 3));
        $this->assertStringContainsString('Code', $lines[0]);
        $this->assertStringContainsString('Nom', $lines[0]);
        $this->assertStringContainsString('1000', $lines[1]);
        $this->assertStringContainsString('Caisse', $lines[1]);
    }
}
