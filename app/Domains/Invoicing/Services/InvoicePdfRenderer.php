<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceLineType;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Renders invoice content (header, line items, totals) into a TCPDF document.
 *
 * Includes all Swiss legal requirements:
 * - Organization legal name, address, VAT number
 * - Customer name, address, VAT number
 * - Invoice number, date, due date, payment terms
 * - QR reference
 * - Customizable header/footer text from organization settings
 *
 * All labels are localized via the organization's locale.
 */
class InvoicePdfRenderer
{
    // ── Layout constants ──────────────────────────────────────────
    private const LEFT_MARGIN = 15;

    private const LOGO_X = 15;

    private const LOGO_Y = 15;

    private const LOGO_WIDTH = 28;

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

    private string $locale = 'en';

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    private function t(string $key): string
    {
        return trans('app.'.$key, [], $this->locale);
    }

    public function renderInvoiceHeader(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        // Organization logo (if configured)
        $logoFullPath = $organization->logo_path
            ? Storage::disk('local')->path($organization->logo_path)
            : null;
        if ($logoFullPath && file_exists($logoFullPath)) {
            $tcpdf->Image($logoFullPath, self::LOGO_X, self::LOGO_Y, self::LOGO_WIDTH);
        }

        // Organization info (top right)
        $tcpdf->SetFont('Helvetica', 'B', 10);
        $tcpdf->SetXY(self::RIGHT_BLOCK_X, 15);
        $tcpdf->Cell(self::RIGHT_BLOCK_W, 5, $organization->legal_name ?? $organization->name, 0, 1, 'R');

        $tcpdf->SetFont('Helvetica', '', 8);
        $orgAddress = array_filter([
            $organization->address,
            trim(($organization->postal_code ?? '').' '.($organization->city ?? '')),
            $organization->canton ? ($organization->country ?? 'CH').' — '.$organization->canton : ($organization->country ?? 'CH'),
        ]);
        foreach ($orgAddress as $line) {
            $tcpdf->SetX(self::RIGHT_BLOCK_X);
            $tcpdf->Cell(self::RIGHT_BLOCK_W, 4, $line, 0, 1, 'R');
        }
        if ($organization->vat_number) {
            $tcpdf->SetX(self::RIGHT_BLOCK_X);
            $tcpdf->SetFont('Helvetica', '', 7);
            $tcpdf->SetTextColor(...self::MUTED_RGB);
            $tcpdf->Cell(self::RIGHT_BLOCK_W, 4, $this->t('pdf_vat_number').': '.$organization->vat_number, 0, 1, 'R');
            $tcpdf->SetTextColor(0, 0, 0);
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
            if ($customer->vat_number) {
                $tcpdf->SetFont('Helvetica', '', 7);
                $tcpdf->SetTextColor(...self::MUTED_RGB);
                $tcpdf->Cell(self::CUSTOMER_W, 4, $this->t('pdf_vat_number').': '.$customer->vat_number, 0, 1);
                $tcpdf->SetTextColor(0, 0, 0);
            }
        }

        // Invoice title
        $tcpdf->SetXY(self::LEFT_MARGIN, self::TITLE_Y);
        $tcpdf->SetFont('Helvetica', 'B', 16);
        $invoiceTypeLabel = $invoice->type === InvoiceType::CreditNote
            ? $this->t('pdf_credit_note')
            : $this->t('pdf_invoice');
        $tcpdf->Cell(0, 8, $invoiceTypeLabel.' '.($invoice->number ?? ''), 0, 1);

        // Invoice metadata block
        $tcpdf->SetFont('Helvetica', '', 9);
        $tcpdf->SetTextColor(...self::MUTED_RGB);

        $metaLines = [];
        $metaLines[] = $this->t('pdf_date').': '.($invoice->issue_date->format('d.m.Y') ?? '');
        $metaLines[] = $this->t('pdf_due_date').': '.($invoice->due_date->format('d.m.Y') ?? '');
        if ($invoice->payment_terms) {
            $metaLines[] = $this->t('pdf_payment_terms').': '.$invoice->payment_terms;
        }
        $metaLines[] = $this->t('pdf_currency').': '.($invoice->currency ?? 'CHF');

        $tcpdf->Cell(0, 5, implode('    ', $metaLines), 0, 1);

        if ($invoice->qr_reference) {
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->Cell(0, 4, $this->t('pdf_reference').': '.$invoice->qr_reference, 0, 1);
        }

        $tcpdf->SetTextColor(0, 0, 0);

        // Customizable header text
        if ($organization->invoice_header_text) {
            $tcpdf->Ln(2);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->MultiCell(self::TABLE_FULL_W, 4, $organization->invoice_header_text, 0, 'L');
        }

        $tcpdf->Ln(4);
    }

    public function renderLineItems(TCPDF $tcpdf, Invoice $invoice): void
    {
        // Table header
        $tcpdf->SetFont('Helvetica', 'B', 8);
        $tcpdf->SetFillColor(...self::HEADER_FILL_RGB);
        $tcpdf->Cell(self::COL_DESC, 6, $this->t('pdf_description'), 0, 0, 'L', true);
        $tcpdf->Cell(self::COL_QTY, 6, $this->t('pdf_quantity'), 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_UNIT, 6, $this->t('pdf_unit_price'), 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_VAT, 6, $this->t('pdf_vat'), 0, 0, 'R', true);
        $tcpdf->Cell(self::COL_AMOUNT, 6, $this->t('pdf_amount'), 0, 1, 'R', true);

        // Lines
        $tcpdf->SetFont('Helvetica', '', 8);
        foreach ($invoice->lines as $line) {
            if ($line->type === InvoiceLineType::Discount && $line->discount_type === 'percentage') {
                $lineTotal = '-'.$line->amount;
                $qtyLabel = '—';
                $priceLabel = $line->unit_price.'%';
            } elseif ($line->type === InvoiceLineType::Discount) {
                $lineTotal = '-'.Money::multiply2((string) $line->quantity, (string) $line->unit_price);
                $qtyLabel = number_format((float) $line->quantity, 2);
                $priceLabel = number_format((float) $line->unit_price, 2);
            } else {
                $lineTotal = Money::multiply2((string) $line->quantity, (string) $line->unit_price);
                $qtyLabel = number_format((float) $line->quantity, 2);
                $priceLabel = number_format((float) $line->unit_price, 2);
            }
            $vatLabel = $line->vatRate ? ($line->vatRate->rate.'%') : '-';

            $descText = str_replace(["\r\n", "\r"], "\n", (string) $line->description);
            $rowY = $tcpdf->GetY();
            $lineCount = max(1, $tcpdf->getNumLines($descText, self::COL_DESC));
            $rowHeight = max(5.0, $lineCount * 4.0);

            $tcpdf->MultiCell(self::COL_DESC, 4, $descText, 0, 'L', false, 0);
            $tcpdf->SetXY(self::LEFT_MARGIN + self::COL_DESC, $rowY);
            $tcpdf->Cell(self::COL_QTY, $rowHeight, $qtyLabel, 0, 0, 'R');
            $tcpdf->Cell(self::COL_UNIT, $rowHeight, $priceLabel, 0, 0, 'R');
            $tcpdf->Cell(self::COL_VAT, $rowHeight, $vatLabel, 0, 0, 'R');
            $tcpdf->Cell(self::COL_AMOUNT, $rowHeight, number_format((float) $lineTotal, 2), 0, 1, 'R');
            $tcpdf->SetY($rowY + $rowHeight);
        }

        // Bottom border
        $tcpdf->Cell(self::TABLE_FULL_W, 0, '', 'T', 1);
    }

    public function renderTotals(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        $tcpdf->Ln(2);
        $tcpdf->SetFont('Helvetica', '', 9);

        // Subtotal
        $tcpdf->Cell(self::TOTALS_LABEL_W, 5, $this->t('pdf_subtotal'), 0, 0, 'R');
        $tcpdf->Cell(self::COL_AMOUNT, 5, number_format((float) $invoice->subtotal, 2), 0, 1, 'R');

        // VAT
        if ((float) $invoice->vat_amount > 0) {
            $tcpdf->Cell(self::TOTALS_LABEL_W, 5, $this->t('pdf_vat_total'), 0, 0, 'R');
            $tcpdf->Cell(self::COL_AMOUNT, 5, number_format((float) $invoice->vat_amount, 2), 0, 1, 'R');
        }

        // Total
        $tcpdf->SetFont('Helvetica', 'B', 11);
        $tcpdf->Cell(self::TOTALS_LABEL_W, 7, $this->t('pdf_total').' '.($invoice->currency ?? 'CHF'), 0, 0, 'R');
        $tcpdf->Cell(self::COL_AMOUNT, 7, number_format((float) $invoice->total, 2), 0, 1, 'R');

        // Notes
        if ($invoice->notes) {
            $tcpdf->Ln(8);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(...self::MUTED_RGB);
            $tcpdf->MultiCell(self::TABLE_FULL_W, 4, $invoice->notes, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }

        // Customizable footer text
        if ($organization->invoice_footer_text) {
            $tcpdf->Ln(4);
            $tcpdf->SetFont('Helvetica', '', 7);
            $tcpdf->SetTextColor(...self::MUTED_RGB);
            $tcpdf->MultiCell(self::TABLE_FULL_W, 3.5, $organization->invoice_footer_text, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }
    }
}
