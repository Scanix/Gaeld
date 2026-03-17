<?php

namespace App\Jobs;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send an invoice notification email to the client.
 *
 * Idempotent: only sends for invoices in 'sent' status.
 * Dispatched from InvoiceService after an invoice is marked as sent.
 *
 * Note: requires a Mailable (InvoiceMailable) and Mail config to be set up.
 */
class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $toEmail,
    ) {}

    public function handle(): void
    {
        // Reload to ensure status is current (idempotency check)
        $invoice = $this->invoice->fresh();

        if (! $invoice || $invoice->status !== 'sent') {
            Log::info('SendInvoiceEmailJob: skipped (invoice not in sent status)', [
                'invoice_id' => $this->invoice->id,
            ]);

            return;
        }

        if (! $this->toEmail) {
            Log::warning('SendInvoiceEmailJob: no recipient email', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        Log::info('SendInvoiceEmailJob: sending invoice email', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'to' => $this->toEmail,
        ]);

        // Mail facade — concrete Mailable to be implemented when email templates are ready.
        // Mail::to($this->toEmail)->send(new InvoiceMailable($invoice));
    }
}
