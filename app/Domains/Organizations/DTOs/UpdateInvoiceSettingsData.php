<?php

namespace App\Domains\Organizations\DTOs;

readonly class UpdateInvoiceSettingsData
{
    public function __construct(
        public ?string $invoiceHeaderText = null,
        public ?string $invoiceFooterText = null,
        public ?string $defaultInvoiceNotes = null,
        public ?string $qrIban = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceHeaderText: $data['invoice_header_text'] ?? null,
            invoiceFooterText: $data['invoice_footer_text'] ?? null,
            defaultInvoiceNotes: $data['default_invoice_notes'] ?? null,
            qrIban: $data['qr_iban'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'invoice_header_text' => $this->invoiceHeaderText,
            'invoice_footer_text' => $this->invoiceFooterText,
            'default_invoice_notes' => $this->defaultInvoiceNotes,
            'qr_iban' => $this->qrIban,
        ];
    }
}
