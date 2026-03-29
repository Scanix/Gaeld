<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a payment reminder e-mail for an overdue invoice.
 */
class SendInvoiceReminderAction
{
    public function execute(Invoice $invoice): void
    {
        if (! $invoice->isOverdue()) {
            throw new InvalidInvoiceStateException('Invoice is not overdue.');
        }

        $customerEmail = $invoice->customer?->email;

        if (! $customerEmail) {
            throw new InvalidInvoiceStateException('Customer has no email address.');
        }

        $invoice->increment('reminder_count');
        $invoice->update(['last_reminded_at' => now()]);

        Mail::to($customerEmail)->send(new InvoiceReminderMail($invoice->fresh()));
    }
}
