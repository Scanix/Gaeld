<?php

namespace App\Domains\Invoicing\Mail;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly int $daysOverdue;

    public function __construct(
        public readonly Invoice $invoice,
    ) {
        $this->daysOverdue = (int) $invoice->due_date->diffInDays(now());
    }

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->invoice->reminder_count <= 1 => __('mail.reminder_subject_first', ['number' => $this->invoice->number]),
            $this->invoice->reminder_count === 2 => __('mail.reminder_subject_second', ['number' => $this->invoice->number]),
            default => __('mail.reminder_subject_final', ['number' => $this->invoice->number]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice-reminder',
        );
    }
}
