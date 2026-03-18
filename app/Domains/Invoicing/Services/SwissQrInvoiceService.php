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
        $invoice->loadMissing(['customer', 'client']);

        $qrBill = QrBill::create();

        // Creditor (the organization issuing the invoice)
        $creditorAddress = StructuredAddress::createWithStreet(
            $organization->legal_name ?? $organization->name,
            $organization->address ?? '',
            null,
            $organization->postal_code ?? '',
            $organization->city ?? '',
            $organization->country ?? 'CH',
        );

        $qrBill->setCreditor($creditorAddress);

        // Creditor IBAN
        $iban = $invoice->qr_iban ?? $organization->qr_iban ?? '';
        $qrBill->setCreditorInformation(
            CreditorInformation::create($iban),
        );

        // Debtor (the customer receiving the invoice)
        $client = $invoice->customer ?? $invoice->client;
        if ($client) {
            $debtorAddress = StructuredAddress::createWithStreet(
                $client->name,
                $client->address ?? '',
                null,
                $client->postal_code ?? '',
                $client->city ?? '',
                $client->country ?? 'CH',
            );

            $qrBill->setUltimateDebtor($debtorAddress);
        }

        // Payment amount
        $currency = $invoice->currency ?? 'CHF';
        if (! in_array($currency, ['CHF', 'EUR'], true)) {
            $currency = 'CHF';
        }

        $qrBill->setPaymentAmountInformation(
            PaymentAmountInformation::create($currency, (float) $invoice->total),
        );

        // Payment reference
        $qrType = $invoice->qr_type ?? 'QRR';

        if ($qrType === 'QRR' && $invoice->qr_reference) {
            $qrBill->setPaymentReference(
                PaymentReference::create(
                    PaymentReference::TYPE_QR,
                    $invoice->qr_reference,
                ),
            );
        } elseif ($qrType === 'SCOR' && $invoice->qr_reference) {
            $qrBill->setPaymentReference(
                PaymentReference::create(
                    PaymentReference::TYPE_SCOR,
                    $invoice->qr_reference,
                ),
            );
        } else {
            $qrBill->setPaymentReference(
                PaymentReference::create(PaymentReference::TYPE_NON),
            );
        }

        // Additional info
        $qrBill->setAdditionalInformation(
            AdditionalInformation::create($invoice->number ?? ''),
        );

        return $qrBill;
    }

    /**
     * Generate QR code image as PNG binary.
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
