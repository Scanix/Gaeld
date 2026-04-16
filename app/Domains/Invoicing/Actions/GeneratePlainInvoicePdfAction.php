<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoicePdfRenderer;
use App\Domains\Organizations\Models\Organization;
use TCPDF;

/**
 * Generates an invoice PDF without a Swiss QR payment slip.
 *
 * Use this when the organisation has not configured QR billing data,
 * or when the user explicitly wants a plain invoice document.
 */
class GeneratePlainInvoicePdfAction
{
    public function __construct(
        private InvoicePdfRenderer $pdfRenderer,
    ) {}

    /**
     * Generate a plain invoice PDF (no QR slip).
     *
     * Returns raw PDF binary string.
     */
    public function execute(Invoice $invoice, Organization $organization, string $language = 'en'): string
    {
        $invoice->loadMissing(['customer', 'lines.vatRate']);

        $tcpdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->SetMargins(15, 15, 15);
        $tcpdf->SetAutoPageBreak(true, 20);
        $tcpdf->AddPage();

        $this->pdfRenderer->setLocale($language);
        $this->pdfRenderer->renderInvoiceHeader($tcpdf, $invoice, $organization);
        $this->pdfRenderer->renderLineItems($tcpdf, $invoice);
        $this->pdfRenderer->renderTotals($tcpdf, $invoice, $organization);

        return $tcpdf->Output('', 'S');
    }
}
