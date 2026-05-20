<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a sent or overdue invoice and reverses its journal entry.
 */
class CancelInvoiceAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Cancelled)) {
            throw new InvalidInvoiceStateException("Cannot cancel an invoice with status: {$invoice->status->value}.");
        }

        return DB::transaction(function () use ($invoice) {
            if ($invoice->journal_entry_id) {
                $invoice->loadMissing('journalEntry.lines');
                $reversal = $this->ledgerService->reverseEntry(
                    $invoice->journalEntry,
                    "Cancellation of {$invoice->number}",
                );
                // Post the reversal immediately for invoice cancellation
                $this->ledgerService->postDraft($reversal);
            }

            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            return $invoice->fresh();
        });
    }
}
