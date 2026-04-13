<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates CSV file content from arrays or Eloquent collections.
 */
class CsvExportService
{
    /**
     * Export data as a downloadable CSV file with UTF-8 BOM for Excel compatibility.
     *
     * @param  string[]  $headers  Column header names.
     * @param  array<int, array>  $rows  Row data (each row is an array of values).
     * @param  string  $filename  The download filename.
     */
    /**
     * @param  array<int, mixed>  $headers
     * @param  array<int, mixed>  $rows
     */
    public function export(array $headers, array $rows, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '\\');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
