<?php

namespace App\Domains\Reporting\Services;

use App\Support\CsvExportService;
use App\Support\PdfExportService;
use Closure;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Shared export logic for all financial reports (CSV / PDF).
 */
class ExportReportService
{
    public function __construct(
        private PdfExportService $pdf,
        private CsvExportService $csv,
    ) {}

    /**
     * Export a report in the given format.
     *
     * @param  string  $format  'pdf' or 'csv'
     * @param  Closure(): HttpResponse  $csvBuilder  Returns a CSV response.
     * @param  Closure(): HttpResponse  $pdfBuilder  Returns a PDF response.
     */
    public function export(string $format, Closure $csvBuilder, Closure $pdfBuilder): HttpResponse
    {
        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        return $format === 'csv' ? $csvBuilder() : $pdfBuilder();
    }

    public function csv(): CsvExportService
    {
        return $this->csv;
    }

    public function pdf(): PdfExportService
    {
        return $this->pdf;
    }
}
