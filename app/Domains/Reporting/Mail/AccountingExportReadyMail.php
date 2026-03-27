<?php

namespace App\Domains\Reporting\Mail;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountingExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $fiscalYear,
        public readonly string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.accounting_export_subject', ['year' => $this->fiscalYear]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.accounting-export-ready',
        );
    }
}
