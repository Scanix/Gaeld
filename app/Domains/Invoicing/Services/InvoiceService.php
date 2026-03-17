<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Record a payment for an invoice with full payment tracking.
     *
     * Creates an InvoicePayment record and posts to ledger:
     *   Debit: Bank Account (1020)
     *   Credit: Accounts Receivable (1100)
     *
     * Supports partial payments. Invoice status is updated to PAID
     * when the full amount has been received.
     */
    public function recordPayment(Invoice $invoice, array $data): InvoicePayment
    {
        $amount = (float) $data['amount'];
        $paymentDate = $data['payment_date'] ?? now()->toDateString();
        $paymentMethod = $data['payment_method'] ?? 'bank';
        $reference = $data['reference'] ?? null;
        $bankAccountCode = $data['bank_account_code'] ?? '1020';

        return DB::transaction(function () use ($invoice, $amount, $paymentDate, $paymentMethod, $reference, $bankAccountCode) {
            $orgId = $invoice->organization_id;

            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);
            $accountsReceivable = $this->ledgerService->resolveAccount($orgId, '1100');

            $paymentRef = $reference ?? 'PAY-' . $invoice->number . '-' . ($invoice->payments()->count() + 1);

            $journalEntry = $this->ledgerService->postEntry($orgId, [
                'date' => $paymentDate,
                'reference' => $paymentRef,
                'description' => "Payment received for {$invoice->number}",
            ], [
                ['account_id' => $bankAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Bank deposit'],
                ['account_id' => $accountsReceivable->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Clear receivable'],
            ]);

            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'reference' => $paymentRef,
            ]);

            // Check if invoice is fully paid
            if ($invoice->fresh()->isFullyPaid()) {
                $invoice->update(['status' => InvoiceStatus::Paid->value]);
            }

            return $payment->load('journalEntry');
        });
    }
}
