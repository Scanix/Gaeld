<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use TCPDF;

/**
 * Renders invoice content (header, line items, totals) into a TCPDF document.
 *
 * Extracted from GenerateQrInvoicePdfAction to isolate PDF rendering
 * logic from QR bill orchestration.
 */
class InvoicePdfRenderer
{
    // ── Layout constants ──────────────────────────────────────────
    private const LEFT_MARGIN = 15;

    private const RIGHT_BLOCK_X = 120;

    private const RIGHT_BLOCK_W = 75;

    private const CUSTOMER_Y = 45;

    private const CUSTOMER_W = 80;

    private const TITLE_Y = 80;

    private const COL_DESC = 80;

    private const COL_QTY = 20;

    private const COL_UNIT = 30;

    private const COL_VAT = 25;

    private const COL_AMOUNT = 25;

    private const TOTALS_LABEL_W = 155;

    private const TABLE_FULL_W = 180;

    private const MUTED_RGB = [100, 100, 100];

    private const HEADER_FILL_RGB = [245, 245, 245];

    public function renderInvoiceHeader(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        // Organization info (top right)
        $tcpdf->SetFont('Helvetica', 'B', 10);
        $tcpdf->SetXY(self::RIGHT_BLOCK_X, 15);
        $tcpdf->Cell(self::RIGHT_BLOCK_W, 5, $organization->legal_name ?? $organization->name, 0, 1, 'R');

        $tcpdf->SetFont('Helvetica', '', 8);
        $orgAddress = array_filter([
            $organization->address,
            trim(($organization->postal_code ?? '').' '.($organization->city ?? '')),
            $organization->country ?? 'CH',
        ]);
        foreach ($orgAddress as $line) {
            $tcpdf->SetX(self::RIGHT_BLOCK_X);
            $tcpdf->Cell(self::RIGHT_BLOCK_W, 4, $line, 0, 1, 'R');
        }
        if ($organization->vat_number) {
            $tcpdf->SetX(self::RIGHT_BLOCK_X);
            $tcpdf->Cell(self::RIGHT_BLOCK_W, 4, $organization->vat_number, 0, 1, 'R');
        }

        // Customer info (top left)
        $customer = $invoice->customer;
        if ($customer) {
            $tcpdf->SetXY(self::LEFT_MARGIN, self::CUSTOMER_Y);
            $tcpdf->SetFont('Helvetica', 'B', 10);
            $tcpdf->Cell(self::CUSTOMER_W, 5, $customer->name, 0, 1);

            $tcpdf->SetFont('Helvetica', '', 9);
            $customerAddress = array_filter([
                $customer->address,
                trim(($customer->postal_code ?? '').' '.($customer->city ?? '')),
                $customer->country ?? 'CH',
            ]);
            foreach ($customerAddress as $line) {
                $tcpdf->Cell(self::CUSTOMER_W, 4, $line, 0, 1);
            }
        }

        // Invoice title
        $tcpdf->SetXY(self::LEFT_MARGIN, self::TITLE_Y);
        $tcpdf->SetFont('Helvetica', 'B', 16);
        $tcpdf->Cell(0, 8, 'Invoice '.($invoice->number ?? ''), 0, 1);

        // Invoice meta
        $tcpdf->SetFont('Helvetica', '', 9);
        $tcpdf->SetTextColor(...self::MUTED_RGB);
        $tcpdf->Cell(0, 5, 'Date: '.($invoice->issue_date?->format('d.m.Y') ?? '').'    Due: '.($invoice->due_date?->format('d.m.Y') ?? '').'    Currency: '.($invoice->currency ?? 'CHF'), 0, 1);
        $tcpdf->SetTextColor(0, 0, 0);

        if ($invoice->qr_reference) {
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(...self::MUTED_RGB);
            $tcpdf->Cell(0, 4, 'Ref: '.$invoice->qr_reference, 0, 1);
            $tcpdf->SetTextColor(0, 0, 0);
        }

        $tcpdf->Ln(4);
    }

    public function renderLineItems(TCPDF $tcpdf, Invoice $invoice): void
    {
        // Table header
        $tcpdf->SetFont('Helvetica', 'B', 8);
        $tcpdf->SetFillColor(...self::HEADER_FILL_RGB);
        $tcpdf->Cell(self::COL_DESC, 6, 'Description', 0, 0, 'L', true);
        $tcpdf->Cell(self::COL_QTY, 6, 'Qty', 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_UNIT, 6, 'Unit Price', 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_VAT, 6, 'VAT', 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_AMOUNT, 6, 'Amount', 0, 1, 'R', true);

        // Lines
        $tcpdf->SetFont('Helvetica', '', 8);
        foreach ($invoice->lines as $line) {
            $lineTotal = bcmul((string) $line->quantity, (string) $line->unit_price, 2);
            $vatLabel = $line->vatRate ? ($line->vatRate->rate.'%') : '-';

            $tcpdf->Cell(self::COL_DESC, 5, $line->description, 0, 0, 'L');
            $tcpdf->Cell(self::COL_QTY, 5, number_format((float) $line->quantity, 2), 0, 0, 'R');
            $tcpdf->Cell(self::COL_UNIT, 5, number_format((float) $line->unit_price, 2), 0, 0, 'R');
            $tcpdf->Cell(self::COL_VAT, 5, $vatLabel, 0, 0, 'R');
            $tcpdf->Cell(self::COL_AMOUNT, 5, number_format((float) $lineTotal, 2), 0, 1, 'R');
        }

        // Bottom border
        $tcpdf->Cell(self::TABLE_FULL_W, 0, '', 'T', 1);
    }

    public function renderTotals(TCPDF $tcpdf, Invoice $invoice): void
    {
        $tcpdf->Ln(2);
        $tcpdf->SetFont('Helvetica', '', 9);

        // Subtotal
        $tcpdf->Cell(self::TOTALS_LABEL_W, 5, 'Subtotal', 0, 0, 'R');
        $tcpdf->Cell(self::COL_AMOUNT, 5, number_format((float) $invoice->subtotal, 2), 0, 1, 'R');

        // VAT
        if ((float) $invoice->vat_amount > 0) {
            $tcpdf->Cell(self::TOTALS_LABEL_W, 5, 'VAT', 0, 0, 'R');
            $tcpdf->Cell(self::COL_AMOUNT, 5, number_format((float) $invoice->vat_amount, 2), 0, 1, 'R');
        }

        // Total
        $tcpdf->SetFont('Helvetica', 'B', 11);
        $tcpdf->Cell(self::TOTALS_LABEL_W, 7, 'Total '.($invoice->currency ?? 'CHF'), 0, 0, 'R');
        $tcpdf->Cell(self::COL_AMOUNT, 7, number_format((float) $invoice->total, 2), 0, 1, 'R');

        // Notes
        if ($invoice->notes) {
            $tcpdf->Ln(8);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(...self::MUTED_RGB);
            $tcpdf->MultiCell(self::TABLE_FULL_W, 4, $invoice->notes, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }
    }
}
