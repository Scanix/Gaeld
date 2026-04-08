<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\QrCode\QrCode;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;

/**
 * Builds Swiss QR-bill payment slips for invoices.
 *
 * Generates the QR code data structure, structured creditor address,
 * and returns the QR payment part as a rendered PNG or PDF.
 */
class SwissQrInvoiceService
{
    private const DEFAULT_COUNTRY = 'CH';

    private const DEFAULT_CURRENCY = 'CHF';

    private const ALLOWED_CURRENCIES = ['CHF', 'EUR'];

    // ──────────────────────────────────────────────────────────────
    //  QR Bill Building
    // ──────────────────────────────────────────────────────────────

    /**
     * Generate a QR reference (QRR) for an invoice.
     *
     * Uses the invoice number to build a deterministic 27-digit reference.
     */
    public function generateQrReference(string $customerIdentification, string $invoiceNumber): string
    {
        $referenceNumber = preg_replace('/[^0-9]/', '', $invoiceNumber);
        $referenceNumber = str_pad($referenceNumber, 20, '0', STR_PAD_LEFT);

        return QrPaymentReferenceGenerator::generate(
            $customerIdentification,
            $referenceNumber,
        );
    }

    /**
     * Build a QrBill object from invoice + organization data.
     */
    public function buildQrBill(Invoice $invoice, Organization $organization): QrBill
    {
        $invoice->loadMissing(['customer']);

        $this->ensureQrReference($invoice, $organization);

        $qrBill = QrBill::create();

        $this->setCreditorInfo($qrBill, $invoice, $organization);
        $this->setDebtorInfo($qrBill, $invoice);
        $this->setPaymentAmount($qrBill, $invoice);
        $this->setPaymentReference($qrBill, $invoice);

        $qrBill->setAdditionalInformation(
            AdditionalInformation::create($invoice->number ?? ''),
        );

        return $qrBill;
    }

    // ──────────────────────────────────────────────────────────────
    //  Bill Component Builders
    // ──────────────────────────────────────────────────────────────

    /**
     * Auto-generate and persist a QR reference when the IBAN is a QR-IBAN
     * and the invoice does not already have one.
     */
    public function ensureQrReference(Invoice $invoice, Organization $organization): void
    {
        if ($invoice->qr_reference) {
            return;
        }

        $iban = $invoice->qr_iban ?? $organization->qr_iban ?? '';
        if (! $this->isQrIban($iban)) {
            return;
        }

        $customerIdentification = str_pad(
            (string) ($invoice->customer_id ? crc32((string) $invoice->customer_id) % 100000 : 0),
            5,
            '0',
            STR_PAD_LEFT,
        );

        $qrReference = $this->generateQrReference(
            $customerIdentification,
            $invoice->number ?? '',
        );

        $invoice->updateQuietly([
            'qr_reference' => $qrReference,
            'qr_type' => 'QRR',
        ]);
    }

    /**
     * Determine whether the given IBAN is a Swiss QR-IBAN (IID 30000–31999).
     */
    private function isQrIban(string $iban): bool
    {
        $iban = preg_replace('/\s+/', '', $iban);

        if (! preg_match('/^(CH|LI)/i', $iban)) {
            return false;
        }

        $iid = (int) substr($iban, 4, 5);

        return $iid >= 30000 && $iid <= 31999;
    }

    private function setCreditorInfo(QrBill $qrBill, Invoice $invoice, Organization $organization): void
    {
        $creditorAddress = StructuredAddress::createWithStreet(
            $organization->legal_name ?? $organization->name,
            $organization->address ?? '',
            null,
            $organization->postal_code ?? '',
            $organization->city ?? '',
            $organization->country ?? self::DEFAULT_COUNTRY,
        );

        $qrBill->setCreditor($creditorAddress);

        $iban = $invoice->qr_iban ?? $organization->qr_iban ?? '';
        $qrBill->setCreditorInformation(
            CreditorInformation::create($iban),
        );
    }

    private function setDebtorInfo(QrBill $qrBill, Invoice $invoice): void
    {
        $customer = $invoice->customer;
        if (! $customer) {
            return;
        }

        $debtorAddress = StructuredAddress::createWithStreet(
            $customer->name,
            $customer->address ?? '',
            null,
            $customer->postal_code ?? '',
            $customer->city ?? '',
            $customer->country ?? self::DEFAULT_COUNTRY,
        );

        $qrBill->setUltimateDebtor($debtorAddress);
    }

    private function setPaymentAmount(QrBill $qrBill, Invoice $invoice): void
    {
        $currency = $invoice->currency ?? self::DEFAULT_CURRENCY;
        if (! in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            $currency = self::DEFAULT_CURRENCY;
        }

        $qrBill->setPaymentAmountInformation(
            PaymentAmountInformation::create($currency, (float) $invoice->total),
        );
    }

    private function setPaymentReference(QrBill $qrBill, Invoice $invoice): void
    {
        $qrType = $invoice->qr_type ?? 'QRR';

        if ($qrType === 'QRR' && $invoice->qr_reference) {
            $refType = PaymentReference::TYPE_QR;
        } elseif ($qrType === 'SCOR' && $invoice->qr_reference) {
            $refType = PaymentReference::TYPE_SCOR;
        } else {
            $refType = PaymentReference::TYPE_NON;
        }

        $qrBill->setPaymentReference(
            PaymentReference::create($refType, $invoice->qr_reference ?? null),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Output & Validation
    // ──────────────────────────────────────────────────────────────

    /**
     * Generate a PNG data URI for the invoice QR code.
     */
    public function generateQrImage(Invoice $invoice, Organization $organization): string
    {
        $qrBill = $this->buildQrBill($invoice, $organization);

        return $qrBill->getQrCode()->getDataUri(QrCode::FILE_FORMAT_PNG);
    }

    /**
     * Get QR bill violations (validation errors).
     *
     * @return array<string>
     */
    public function validate(Invoice $invoice, Organization $organization): array
    {
        $qrBill = $this->buildQrBill($invoice, $organization);

        $violations = $qrBill->getViolations();

        return array_map(
            fn ($violation) => $violation->getMessage(),
            iterator_to_array($violations),
        );
    }
}
