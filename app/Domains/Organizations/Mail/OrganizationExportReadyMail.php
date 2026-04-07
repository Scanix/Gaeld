<?php

namespace App\Domains\Organizations\Mail;

use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.org_export_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization-export-ready',
            with: [
                'user' => $this->user,
                'downloadUrl' => $this->downloadUrl,
            ],
        );
    }
}
