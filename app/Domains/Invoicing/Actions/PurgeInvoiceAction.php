<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes a cancelled or soft-deleted invoice and its line items.
 */
class PurgeInvoiceAction
{
    public function execute(Invoice $invoice): void
    {
        if (! $invoice->trashed() && $invoice->status->value !== 'cancelled') {
            throw new InvalidInvoiceStateException('Only cancelled or already deleted invoices can be permanently removed.');
        }

        DB::transaction(function () use ($invoice) {
            $this->deleteJournalEntries($invoice);
            $invoice->lines()->forceDelete();
            $invoice->forceDelete();
        });
    }

    private function deleteJournalEntries(Invoice $invoice): void
    {
        if (! $invoice->journal_entry_id) {
            return;
        }

        // Delete the reversal entry (REV-{number}) if it exists
        JournalEntry::where('organization_id', $invoice->organization_id)
            ->where('reference', 'REV-'.$invoice->number)
            ->delete();

        // Delete the original journal entry (cascades to transaction_lines & vat_entries)
        JournalEntry::where('id', $invoice->journal_entry_id)->delete();
    }
}
