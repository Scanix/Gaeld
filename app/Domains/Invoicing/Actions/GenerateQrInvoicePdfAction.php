<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use App\Domains\Organizations\Models\Organization;
use Sprain\SwissQrBill\PaymentPart\Output\DisplayOptions;
use Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput;
use TCPDF;

class GenerateQrInvoicePdfAction
{
    public function __construct(
        private SwissQrInvoiceService $qrService,
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

        // --- INVOICE HEADER ---
        $this->renderInvoiceHeader($tcpdf, $invoice, $organization);

        // --- LINE ITEMS TABLE ---
        $this->renderLineItems($tcpdf, $invoice);

        // --- TOTALS ---
        $this->renderTotals($tcpdf, $invoice);

        // --- QR PAYMENT SLIP (bottom of page) ---
        $qrBill = $this->qrService->buildQrBill($invoice, $organization);
        $langMap = ['en' => 'en', 'de' => 'de', 'fr' => 'fr', 'it' => 'it', 'rm' => 'de'];
        $qrLang = $langMap[$language] ?? 'en';

        $output = new TcPdfOutput($qrBill, $qrLang, $tcpdf);
        $displayOptions = (new DisplayOptions())->setPrintable(false);
        $output->setDisplayOptions($displayOptions)->getPaymentPart();

        return $tcpdf->Output('', 'S');
    }

    private function renderInvoiceHeader(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        // Organization info (top right)
        $tcpdf->SetFont('Helvetica', 'B', 10);
        $tcpdf->SetXY(120, 15);
        $tcpdf->Cell(75, 5, $organization->legal_name ?? $organization->name, 0, 1, 'R');

        $tcpdf->SetFont('Helvetica', '', 8);
        $orgAddress = array_filter([
            $organization->address,
            trim(($organization->postal_code ?? '') . ' ' . ($organization->city ?? '')),
            $organization->country ?? 'CH',
        ]);
        foreach ($orgAddress as $line) {
            $tcpdf->SetX(120);
            $tcpdf->Cell(75, 4, $line, 0, 1, 'R');
        }
        if ($organization->vat_number) {
            $tcpdf->SetX(120);
            $tcpdf->Cell(75, 4, $organization->vat_number, 0, 1, 'R');
        }

        // Customer info (top left)
        $customer = $invoice->customer;
        if ($customer) {
            $tcpdf->SetXY(15, 45);
            $tcpdf->SetFont('Helvetica', 'B', 10);
            $tcpdf->Cell(80, 5, $customer->name, 0, 1);

            $tcpdf->SetFont('Helvetica', '', 9);
            $customerAddress = array_filter([
                $customer->address,
                trim(($customer->postal_code ?? '') . ' ' . ($customer->city ?? '')),
                $customer->country ?? 'CH',
            ]);
            foreach ($customerAddress as $line) {
                $tcpdf->Cell(80, 4, $line, 0, 1);
            }
        }

        // Invoice title
        $tcpdf->SetXY(15, 80);
        $tcpdf->SetFont('Helvetica', 'B', 16);
        $tcpdf->Cell(0, 8, 'Invoice ' . ($invoice->number ?? ''), 0, 1);

        // Invoice meta
        $tcpdf->SetFont('Helvetica', '', 9);
        $tcpdf->SetTextColor(100, 100, 100);
        $tcpdf->Cell(0, 5, 'Date: ' . ($invoice->issue_date?->format('d.m.Y') ?? '') . '    Due: ' . ($invoice->due_date?->format('d.m.Y') ?? '') . '    Currency: ' . ($invoice->currency ?? 'CHF'), 0, 1);
        $tcpdf->SetTextColor(0, 0, 0);

        if ($invoice->qr_reference) {
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(100, 100, 100);
            $tcpdf->Cell(0, 4, 'Ref: ' . $invoice->qr_reference, 0, 1);
            $tcpdf->SetTextColor(0, 0, 0);
        }

        $tcpdf->Ln(4);
    }

    private function renderLineItems(TCPDF $tcpdf, Invoice $invoice): void
    {
        $y = $tcpdf->GetY();

        // Table header
        $tcpdf->SetFont('Helvetica', 'B', 8);
        $tcpdf->SetFillColor(245, 245, 245);
        $tcpdf->Cell(80, 6, 'Description', 0, 0, 'L', true);
        $tcpdf->Cell(20, 6, 'Qty', 0, 0, 'R', true);
        $tcpdf->Cell(30, 6, 'Unit Price', 0, 0, 'R', true);
        $tcpdf->Cell(25, 6, 'VAT', 0, 0, 'R', true);
        $tcpdf->Cell(25, 6, 'Amount', 0, 1, 'R', true);

        // Lines
        $tcpdf->SetFont('Helvetica', '', 8);
        foreach ($invoice->lines as $line) {
            $lineTotal = bcmul((string) $line->quantity, (string) $line->unit_price, 2);
            $vatLabel = $line->vatRate ? ($line->vatRate->rate . '%') : '-';

            $tcpdf->Cell(80, 5, $line->description, 0, 0, 'L');
            $tcpdf->Cell(20, 5, number_format((float) $line->quantity, 2), 0, 0, 'R');
            $tcpdf->Cell(30, 5, number_format((float) $line->unit_price, 2), 0, 0, 'R');
            $tcpdf->Cell(25, 5, $vatLabel, 0, 0, 'R');
            $tcpdf->Cell(25, 5, number_format((float) $lineTotal, 2), 0, 1, 'R');
        }

        // Bottom border
        $tcpdf->Cell(180, 0, '', 'T', 1);
    }

    private function renderTotals(TCPDF $tcpdf, Invoice $invoice): void
    {
        $tcpdf->Ln(2);
        $tcpdf->SetFont('Helvetica', '', 9);

        // Subtotal
        $tcpdf->Cell(155, 5, 'Subtotal', 0, 0, 'R');
        $tcpdf->Cell(25, 5, number_format((float) $invoice->subtotal, 2), 0, 1, 'R');

        // VAT
        if ((float) $invoice->vat_amount > 0) {
            $tcpdf->Cell(155, 5, 'VAT', 0, 0, 'R');
            $tcpdf->Cell(25, 5, number_format((float) $invoice->vat_amount, 2), 0, 1, 'R');
        }

        // Total
        $tcpdf->SetFont('Helvetica', 'B', 11);
        $tcpdf->Cell(155, 7, 'Total ' . ($invoice->currency ?? 'CHF'), 0, 0, 'R');
        $tcpdf->Cell(25, 7, number_format((float) $invoice->total, 2), 0, 1, 'R');

        // Notes
        if ($invoice->notes) {
            $tcpdf->Ln(8);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(100, 100, 100);
            $tcpdf->MultiCell(180, 4, $invoice->notes, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }
    }
}
