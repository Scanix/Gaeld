<?php

namespace App\Domains\Invoicing\Notifications;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoicePaymentRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invoice $invoice,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice_payment_recorded',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number ?? '',
            'url' => route('invoices.show', $this->invoice),
        ];
    }
}
