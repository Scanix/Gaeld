<?php

namespace App\Domains\Organizations\DTOs;

readonly class UpdateCommunicationsData
{
    public function __construct(
        public ?string $invoiceEmailSubject = null,
        public ?string $invoiceEmailBody = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceEmailSubject: $data['invoice_email_subject'] ?? null,
            invoiceEmailBody: $data['invoice_email_body'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'invoice_email_subject' => $this->invoiceEmailSubject,
            'invoice_email_body' => $this->invoiceEmailBody,
        ];
    }
}
