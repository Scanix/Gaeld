<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Reverts a sent invoice back to draft so its number, dates, customer or line
 * items can be corrected, then re-finalized. Reverses the invoice's journal
 * entry the same way cancellation does. Only allowed while no payment has
 * been recorded yet, so the accounting trail stays consistent.
 */
class RevertInvoiceToDraftAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Draft)) {
            throw new InvalidInvoiceStateException("Cannot revert an invoice with status: {$invoice->status->value} to draft.");
        }

        if ($invoice->payments()->count() > 0) {
            throw new InvalidInvoiceStateException('Cannot revert to draft: a payment has already been recorded for this invoice.');
        }

        return DB::transaction(function () use ($invoice) {
            if ($invoice->journal_entry_id) {
                $invoice->loadMissing('journalEntry.lines');
                $reversal = $this->ledgerService->reverseEntry(
                    $invoice->journalEntry,
                    "Revert to draft of {$invoice->number}",
                );
                $this->ledgerService->postDraft($reversal);
            }

            $invoice->update(['status' => InvoiceStatus::Draft]);

            return $invoice->fresh();
        });
    }
}
