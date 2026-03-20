<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

class FinalizeInvoiceAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvalidInvoiceStateException("Only draft invoices can be finalized (current status: {$invoice->status->value}).");
        }

        if ($invoice->lines()->count() === 0) {
            throw new InvalidInvoiceStateException('Cannot finalize an invoice with no line items.');
        }

        return $this->postToLedger($invoice);
    }

    /**
     * Post an invoice to the ledger.
     *
     * Accounting effect:
     *   Debit  1100 Accounts Receivable  (invoice total)
     *   Credit 3000 Revenue from Services (invoice total)
     */
    private function postToLedger(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $orgId = $invoice->organization_id;

            $ar = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);
            $revenue = $this->ledgerService->resolveAccount($orgId, AccountCode::REVENUE);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $invoice->issue_date->toDateString(),
                reference: $invoice->number,
                description: "Invoice {$invoice->number} — " . ($invoice->customer?->name ?? 'N/A'),
                lines: [
                    new JournalLineData(accountId: $ar->id, debit: $invoice->total, credit: '0', description: 'Accounts Receivable'),
                    new JournalLineData(accountId: $revenue->id, debit: '0', credit: $invoice->total, description: 'Revenue'),
                ],
            ));

            $invoice->update([
                'status' => InvoiceStatus::Sent,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $invoice->fresh(['lines', 'customer', 'journalEntry.lines']);
        });
    }
}
