<?php

namespace App\Domains\Invoicing\Mail;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Organization $organization,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->organization->invoice_email_subject
            ? $this->replacePlaceholders($this->organization->invoice_email_subject)
            : __('mail.invoice_subject', ['number' => $this->invoice->number]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice-sent',
            with: [
                'body' => $this->organization->invoice_email_body
                    ? $this->replacePlaceholders($this->organization->invoice_email_body)
                    : null,
            ],
        );
    }

    private function replacePlaceholders(string $text): string
    {
        return str_replace(
            ['{customer_name}', '{invoice_number}', '{amount}', '{due_date}', '{organization_name}'],
            [
                $this->invoice->customer->name ?? '',
                $this->invoice->number ?? '',
                number_format($this->invoice->total ?? 0, 2).' '.($this->invoice->currency ?? 'CHF'),
                $this->invoice->due_date->format('d.m.Y'),
                $this->organization->name ?? '',
            ],
            $text,
        );
    }
}
