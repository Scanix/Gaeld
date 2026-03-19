<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\QrCode\QrCode;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;

class SwissQrInvoiceService
{
    private const DEFAULT_COUNTRY = 'CH';
    private const DEFAULT_CURRENCY = 'CHF';
    private const ALLOWED_CURRENCIES = ['CHF', 'EUR'];

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
