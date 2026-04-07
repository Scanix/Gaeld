<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\QrBillValidationException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoicePdfRenderer;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use App\Domains\Organizations\Models\Organization;
use Sprain\SwissQrBill\PaymentPart\Output\DisplayOptions;
use Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput;
use TCPDF;

class GenerateQrInvoicePdfAction
{
    public function __construct(
        private SwissQrInvoiceService $qrService,
        private InvoicePdfRenderer $pdfRenderer,
    ) {}

    /**
     * Generate a PDF invoice with Swiss QR payment slip.
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
        $tcpdf->SetAutoPageBreak(false);
        $tcpdf->AddPage();

        // --- INVOICE CONTENT ---
        $this->pdfRenderer->setLocale($language);
        $this->pdfRenderer->renderInvoiceHeader($tcpdf, $invoice, $organization);
        $this->pdfRenderer->renderLineItems($tcpdf, $invoice);
        $this->pdfRenderer->renderTotals($tcpdf, $invoice, $organization);

        // --- QR PAYMENT SLIP (bottom of page) ---
        $violations = $this->qrService->validate($invoice, $organization);
        if ($violations !== []) {
            throw new QrBillValidationException($violations);
        }

        $qrBill = $this->qrService->buildQrBill($invoice, $organization);
        $langMap = ['en' => 'en', 'de' => 'de', 'fr' => 'fr', 'it' => 'it', 'rm' => 'de'];
        $qrLang = $langMap[$language] ?? 'en';

        $output = new TcPdfOutput($qrBill, $qrLang, $tcpdf);
        $displayOptions = (new DisplayOptions)->setPrintable(false);
        $output->setDisplayOptions($displayOptions)->getPaymentPart();

        return $tcpdf->Output('', 'S');
    }
}
