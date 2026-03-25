<?php

namespace App\Domains\Users\Mail;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataExportReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.data_export_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.data-export-ready',
        );
    }
}
