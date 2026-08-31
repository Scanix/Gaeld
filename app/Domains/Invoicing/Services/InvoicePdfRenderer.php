<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceLineType;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Support\InvoicePdfStyle;
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

    /**
     * Draw the Swiss letter standard (SN 010130 / DIN 5008) fold and punch
     * marks on the left edge of the page so the sheet can be folded in three
     * for a C5/C6 window envelope, with the recipient address showing through
     * the window.
     *
     * - Top fold mark at 105 mm (first horizontal fold)
     * - Centre punch / hole-punch reference at 148.5 mm
     * - Bottom fold mark at 210 mm (second horizontal fold)
     */
    public function renderFoldMarks(TCPDF $tcpdf): void
    {
        $tcpdf->SetDrawColor(...InvoicePdfStyle::COLOR_GRAY);
        $tcpdf->SetLineWidth(0.1);

        $marks = [
            InvoicePdfStyle::FOLD_MARK_TOP_Y,
            InvoicePdfStyle::PUNCH_MARK_Y,
            InvoicePdfStyle::FOLD_MARK_BOTTOM_Y,
        ];
        foreach ($marks as $y) {
            $tcpdf->Line(
                InvoicePdfStyle::FOLD_MARK_X,
                $y,
                InvoicePdfStyle::FOLD_MARK_X + InvoicePdfStyle::FOLD_MARK_LENGTH,
                $y,
            );
        }

        $tcpdf->SetDrawColor(0, 0, 0);
        $tcpdf->SetLineWidth(0.2);
    }

    public function renderInvoiceHeader(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        // Organization logo (if configured)
        $logoFullPath = $organization->logo_path
            ? Storage::disk('local')->path($organization->logo_path)
            : null;
        if ($logoFullPath && file_exists($logoFullPath)) {
            $tcpdf->Image($logoFullPath, InvoicePdfStyle::LOGO_X, InvoicePdfStyle::LOGO_Y, InvoicePdfStyle::LOGO_WIDTH);
        }

        // Organization info (top left, sender position on Swiss business letters)
        $tcpdf->SetFont('Helvetica', 'B', 10);
        $organizationY = $logoFullPath && file_exists($logoFullPath) ? 30 : InvoicePdfStyle::MARGIN_TOP;
        $tcpdf->SetXY(InvoicePdfStyle::ORGANIZATION_X, $organizationY);
        $tcpdf->Cell(InvoicePdfStyle::ORGANIZATION_WIDTH, 5, $organization->legal_name ?? $organization->name, 0, 1, 'L');

        $tcpdf->SetFont('Helvetica', '', 8);
        $orgAddress = array_filter([
            $organization->address,
            trim(($organization->postal_code ?? '').' '.($organization->city ?? '')),
            $organization->canton ? ($organization->country ?? 'CH').' — '.$organization->canton : ($organization->country ?? 'CH'),
        ]);
        foreach ($orgAddress as $line) {
            $tcpdf->SetX(InvoicePdfStyle::ORGANIZATION_X);
            $tcpdf->Cell(InvoicePdfStyle::ORGANIZATION_WIDTH, 4, $line, 0, 1, 'L');
        }
        if ($organization->vat_number) {
            $tcpdf->SetX(InvoicePdfStyle::ORGANIZATION_X);
            $tcpdf->SetFont('Helvetica', '', 7);
            $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_GRAY);
            $tcpdf->Cell(InvoicePdfStyle::ORGANIZATION_WIDTH, 4, $this->t('pdf_vat_number').': '.$organization->vat_number, 0, 1, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }

        // Customer info — placed in the right-hand Swiss SN 010130 / DIN 5008
        // address window; the sender remains on the left.
        $customer = $invoice->customer;
        $customerDetails = $invoice->customer_snapshot;
        if ($customerDetails === null && $customer !== null) {
            $customerDetails = $customer->toInvoiceSnapshot();
        }

        if ($customerDetails !== null) {
            $tcpdf->SetXY(InvoicePdfStyle::CUSTOMER_X, InvoicePdfStyle::CUSTOMER_INFO_Y);
            $tcpdf->SetFont('Helvetica', 'B', 10);
            $tcpdf->Cell(InvoicePdfStyle::CUSTOMER_WIDTH, 5, $customerDetails['name'], 0, 1, 'L');

            $tcpdf->SetFont('Helvetica', '', 9);
            $customerAddress = array_filter([
                $customerDetails['address'],
                trim(($customerDetails['postal_code'] ?? '').' '.($customerDetails['city'] ?? '')),
                $customerDetails['country'],
            ]);
            foreach ($customerAddress as $line) {
                $tcpdf->SetX(InvoicePdfStyle::CUSTOMER_X);
                $tcpdf->Cell(InvoicePdfStyle::CUSTOMER_WIDTH, 4, $line, 0, 1, 'L');
            }
            if ($customerDetails['vat_number']) {
                $tcpdf->SetFont('Helvetica', '', 7);
                $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_GRAY);
                $tcpdf->SetX(InvoicePdfStyle::CUSTOMER_X);
                $tcpdf->Cell(InvoicePdfStyle::CUSTOMER_WIDTH, 4, $this->t('pdf_vat_number').': '.$customerDetails['vat_number'], 0, 1, 'L');
                $tcpdf->SetTextColor(0, 0, 0);
            }
        }

        // Invoice title
        $tcpdf->SetXY(InvoicePdfStyle::MARGIN_LEFT, InvoicePdfStyle::INVOICE_TITLE_Y);
        $tcpdf->SetFont('Helvetica', 'B', InvoicePdfStyle::FONT_INVOICE_TITLE);
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_ACCENT);
        $invoiceTypeLabel = $invoice->type === InvoiceType::CreditNote
            ? $this->t('pdf_credit_note')
            : $this->t('pdf_invoice');
        $tcpdf->Cell(0, 8, $invoiceTypeLabel.' '.($invoice->number ?? ''), 0, 1);

        // Invoice metadata block
        $tcpdf->SetFont('Helvetica', '', 9);
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_GRAY);

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

        if (($invoice->tax_treatment ?? InvoiceTaxTreatment::Standard) === InvoiceTaxTreatment::ReverseCharge) {
            $tcpdf->SetFont('Helvetica', 'B', 8);
            $tcpdf->Cell(0, 4, $this->t('pdf_reverse_charge'), 0, 1);
        }

        $tcpdf->SetTextColor(0, 0, 0);

        // Customizable header text
        if ($organization->invoice_header_text) {
            $tcpdf->Ln(2);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->MultiCell(InvoicePdfStyle::COL_TOTAL_WIDTH, 4, $organization->invoice_header_text, 0, 'L');
        }

        $tcpdf->Ln(4);
    }

    public function renderLineItems(TCPDF $tcpdf, Invoice $invoice): void
    {
        // Table header
        $tcpdf->SetFont('Helvetica', 'B', InvoicePdfStyle::FONT_TABLE_HEADER);
        $tcpdf->SetFillColor(...InvoicePdfStyle::COLOR_FILL);
        $tcpdf->Cell(InvoicePdfStyle::COL_DESCRIPTION, 6, $this->t('pdf_description'), 0, 0, 'L', true);
        $tcpdf->Cell(InvoicePdfStyle::COL_QUANTITY, 6, $this->t('pdf_quantity'), 0, 0, 'R', true);
        $tcpdf->Cell(InvoicePdfStyle::COL_UNIT_PRICE, 6, $this->t('pdf_unit_price'), 0, 0, 'R', true);
        $tcpdf->Cell(InvoicePdfStyle::COL_VAT, 6, $this->t('pdf_vat'), 0, 0, 'R', true);
        $tcpdf->Cell(InvoicePdfStyle::COL_AMOUNT, 6, $this->t('pdf_amount'), 0, 1, 'R', true);

        // Lines
        $tcpdf->SetFont('Helvetica', '', InvoicePdfStyle::FONT_TABLE_ROW);
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
            $lineCount = max(1, $tcpdf->getNumLines($descText, InvoicePdfStyle::COL_DESCRIPTION));
            $rowHeight = max(5.0, $lineCount * 4.0);

            $tcpdf->MultiCell(InvoicePdfStyle::COL_DESCRIPTION, 4, $descText, 0, 'L', false, 0);
            $tcpdf->SetXY(InvoicePdfStyle::MARGIN_LEFT + InvoicePdfStyle::COL_DESCRIPTION, $rowY);
            $tcpdf->Cell(InvoicePdfStyle::COL_QUANTITY, $rowHeight, $qtyLabel, 0, 0, 'R');
            $tcpdf->Cell(InvoicePdfStyle::COL_UNIT_PRICE, $rowHeight, $priceLabel, 0, 0, 'R');
            $tcpdf->Cell(InvoicePdfStyle::COL_VAT, $rowHeight, $vatLabel, 0, 0, 'R');
            $tcpdf->Cell(InvoicePdfStyle::COL_AMOUNT, $rowHeight, number_format((float) $lineTotal, 2), 0, 1, 'R');
            $tcpdf->SetY($rowY + $rowHeight);
        }

        // Bottom border
        $tcpdf->Cell(InvoicePdfStyle::COL_TOTAL_WIDTH, 0, '', 'T', 1);
    }

    public function renderTotals(TCPDF $tcpdf, Invoice $invoice, Organization $organization): void
    {
        $tcpdf->Ln(2);
        $tcpdf->SetFont('Helvetica', '', InvoicePdfStyle::FONT_TOTALS);

        // Subtotal
        $tcpdf->Cell(InvoicePdfStyle::TOTALS_LABEL_WIDTH, 5, $this->t('pdf_subtotal'), 0, 0, 'R');
        $tcpdf->Cell(InvoicePdfStyle::COL_AMOUNT, 5, number_format((float) $invoice->subtotal, 2), 0, 1, 'R');

        // VAT
        if ((float) $invoice->vat_amount > 0) {
            $tcpdf->Cell(InvoicePdfStyle::TOTALS_LABEL_WIDTH, 5, $this->t('pdf_vat_total'), 0, 0, 'R');
            $tcpdf->Cell(InvoicePdfStyle::COL_AMOUNT, 5, number_format((float) $invoice->vat_amount, 2), 0, 1, 'R');
        }

        // Total
        $tcpdf->SetFont('Helvetica', 'B', InvoicePdfStyle::FONT_TOTALS_GRAND);
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_ACCENT);
        $tcpdf->Cell(InvoicePdfStyle::TOTALS_LABEL_WIDTH, 7, $this->t('pdf_total').' '.($invoice->currency ?? 'CHF'), 0, 0, 'R');
        $tcpdf->Cell(InvoicePdfStyle::COL_AMOUNT, 7, number_format((float) $invoice->total, 2), 0, 1, 'R');
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_BLACK);

        // Notes
        if ($invoice->notes) {
            $tcpdf->Ln(8);
            $tcpdf->SetFont('Helvetica', '', 8);
            $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_GRAY);
            $tcpdf->MultiCell(InvoicePdfStyle::COL_TOTAL_WIDTH, 4, $invoice->notes, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }

        // Customizable footer text
        if ($organization->invoice_footer_text) {
            $tcpdf->Ln(4);
            $tcpdf->SetFont('Helvetica', '', 7);
            $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_GRAY);
            $tcpdf->MultiCell(InvoicePdfStyle::COL_TOTAL_WIDTH, 3.5, $organization->invoice_footer_text, 0, 'L');
            $tcpdf->SetTextColor(0, 0, 0);
        }
    }

    public function renderFooter(TCPDF $tcpdf): void
    {
        $footerY = $tcpdf->getPageHeight() - 12;

        $tcpdf->SetDrawColor(...InvoicePdfStyle::COLOR_RULE);
        $tcpdf->SetLineWidth(0.2);
        $tcpdf->Line(
            InvoicePdfStyle::MARGIN_LEFT,
            $footerY - 2,
            $tcpdf->getPageWidth() - InvoicePdfStyle::MARGIN_RIGHT,
            $footerY - 2,
        );
        $tcpdf->SetXY(InvoicePdfStyle::MARGIN_LEFT, $footerY);
        $tcpdf->SetFont('Helvetica', '', 7);
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_LIGHT);
        $tcpdf->Cell(70, 4, '© '.now()->year.' Gäld', 0, 0, 'L');
        $tcpdf->Cell(InvoicePdfStyle::COL_TOTAL_WIDTH - 70, 4, $tcpdf->getAliasNumPage().'/'.$tcpdf->getAliasNbPages(), 0, 1, 'R');
        $tcpdf->SetTextColor(...InvoicePdfStyle::COLOR_BLACK);
        $tcpdf->SetLineWidth(0.2);
    }
}
