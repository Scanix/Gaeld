<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Models\Invoice;

class InvoiceService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Finalize and post an invoice to the ledger.
     *
     * Delegates to LedgerService::postInvoice() which handles:
     *   Debit  1100 Accounts Receivable
     *   Credit 3000 Revenue from Services
     */
    public function finalizeInvoice(Invoice $invoice): Invoice
    {
        return $this->ledgerService->postInvoice($invoice);
    }

    /**
     * Record a payment for an invoice.
     *
     * Debit: Bank Account (1020)
     * Credit: Accounts Receivable (1100)
     */
    public function recordPayment(Invoice $invoice, float $amount, string $bankAccountCode = '1020'): Invoice
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new \DomainException('Invoice is already paid.');
        }

        $orgId = $invoice->organization_id;

        $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);
        $accountsReceivable = $this->ledgerService->resolveAccount($orgId, '1100');

        $this->ledgerService->postEntry($orgId, [
            'date' => now()->toDateString(),
            'reference' => 'PAY-' . $invoice->number,
            'description' => "Payment received for {$invoice->number}",
        ], [
            ['account_id' => $bankAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Bank deposit'],
            ['account_id' => $accountsReceivable->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Clear receivable'],
        ]);

        if (bccomp((string) $amount, (string) $invoice->total, 2) >= 0) {
            $invoice->update(['status' => Invoice::STATUS_PAID]);
        }

        return $invoice->fresh();
    }
}
