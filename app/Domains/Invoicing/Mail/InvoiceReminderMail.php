<?php

namespace App\Domains\Invoicing\Mail;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly int $daysOverdue;

    public readonly string $amountDue;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Organization $organization,
        public readonly int $reminderNumber,
    ) {
        $this->daysOverdue = (int) $invoice->due_date->diffInDays(now());
        $this->amountDue = $invoice->amountDue();
    }

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->reminderNumber <= 1 => __('mail.reminder_subject_first', ['number' => $this->invoice->number, 'organization' => $this->organization->name]),
            $this->reminderNumber === 2 => __('mail.reminder_subject_second', ['number' => $this->invoice->number, 'organization' => $this->organization->name]),
            default => __('mail.reminder_subject_final', ['number' => $this->invoice->number, 'organization' => $this->organization->name]),
        };

        return new Envelope(
            from: $this->fromAddress(),
            replyTo: $this->replyToAddresses(),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice-reminder',
            with: [
                'organization' => $this->organization,
                'amountDue' => $this->amountDue,
            ],
        );
    }

    private function fromAddress(): ?Address
    {
        $address = config('mail.from.address');

        return $address ? new Address($address, $this->organization->name) : null;
    }

    /**
     * @return array<int, Address>
     */
    private function replyToAddresses(): array
    {
        return $this->organization->contact_email
            ? [new Address($this->organization->contact_email, $this->organization->name)]
            : [];
    }
}
