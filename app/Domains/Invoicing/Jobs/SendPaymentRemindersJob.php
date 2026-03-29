<?php

namespace App\Domains\Invoicing\Jobs;

use App\Domains\Invoicing\Actions\SendInvoiceReminderAction;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Minimum days between automated reminders */
    private const COOLDOWN_DAYS = 7;

    public function handle(SendInvoiceReminderAction $action): void
    {
        $overdueInvoices = Invoice::withoutGlobalScope('organization')
            ->overdue()
            ->whereHas('customer', fn ($q) => $q->whereNotNull('email'))
            ->where(function ($q) {
                $q->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', now()->subDays(self::COOLDOWN_DAYS));
            })
            ->get();

        foreach ($overdueInvoices as $invoice) {
            try {
                $action->execute($invoice);

                Log::info('SendPaymentRemindersJob: reminder sent', [
                    'invoice_id' => $invoice->id,
                    'reminder_count' => $invoice->reminder_count,
                ]);
            } catch (\DomainException|\RuntimeException|\InvalidArgumentException $e) {
                Log::error('SendPaymentRemindersJob: failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
