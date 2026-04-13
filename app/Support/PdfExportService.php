<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders Blade views to PDF using DomPDF.
 */
class PdfExportService
{
    public function __construct(
        private string $paperSize = 'A4',
        private string $orientation = 'portrait',
    ) {}

    /**
     * Render a Blade view to a downloadable PDF response.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function download(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper($this->paperSize, $this->orientation)
            ->download($filename);
    }

    /**
     * Render a Blade view to an inline (streamed) PDF response.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function stream(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper($this->paperSize, $this->orientation)
            ->stream($filename);
    }
}
