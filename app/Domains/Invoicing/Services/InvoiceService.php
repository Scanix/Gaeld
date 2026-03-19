<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
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
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException  When account code not found
     */
    public function recordPayment(Invoice $invoice, RecordPaymentData $data): InvoicePayment
    {
        $amount = $data->amount;
        $paymentDate = $data->paymentDate;
        $paymentMethod = $data->paymentMethod;
        $reference = $data->reference;
        $bankAccountCode = $data->bankAccountCode ?? AccountCode::BANK_CASH;

        return DB::transaction(function () use ($invoice, $amount, $paymentDate, $paymentMethod, $reference, $bankAccountCode) {
            $orgId = $invoice->organization_id;

            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);
            $accountsReceivable = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);

            $paymentRef = $reference ?? 'PAY-' . $invoice->number . '-' . ($invoice->payments()->count() + 1);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $paymentDate,
                reference: $paymentRef,
                description: "Payment received for {$invoice->number}",
                lines: [
                    new JournalLineData(accountId: $bankAccount->id, debit: $amount, credit: 0, description: 'Bank deposit'),
                    new JournalLineData(accountId: $accountsReceivable->id, debit: 0, credit: $amount, description: 'Clear receivable'),
                ],
            ));

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
