<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles bank transactions against invoices.
 *
 * Handles manual reconciliation, match confirmation, and the shared
 * validate→pay→update sequence for invoice-based reconciliation.
 */
class InvoiceReconciler
{
    use ReconciliationPreconditions;
    use ReconciliationReference;

    private const REFERENCE_PREFIX = 'REC-';

    public function __construct(
        private LedgerService $ledgerService,
        private InvoiceAccountingService $invoiceAccountingService,
        private BankingService $bankingService,
    ) {}

    /**
     * Manually reconcile a bank transaction with an invoice.
     *
     * @throws AlreadyReconciledException
     * @throws UnlinkedBankAccountException
     */
    public function reconcileWithInvoice(
        BankTransaction $transaction,
        Invoice $invoice,
        string $bankAccountCode = AccountCode::BANK_CASH,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $invoice, $bankAccountCode) {
            $bankAccount = $transaction->bankAccount;

            $this->validatePreconditions($transaction, $bankAccount);
            $this->recordPaymentAndReconcile($transaction, $invoice, $bankAccount, $bankAccountCode);

            return $transaction->fresh(['journalEntry.lines', 'matchedInvoice', 'bankAccount']);
        });
    }

    /**
     * Confirm a match: record the payment and mark the transaction as reconciled.
     *
     * @throws AlreadyReconciledException
     * @throws InvalidPaymentException
     * @throws UnlinkedBankAccountException
     */
    public function confirmMatch(BankMatch $match): BankTransaction
    {
        $transaction = $match->bankTransaction;
        $invoice = $match->invoice;
        $bankAccount = $transaction->bankAccount;

        return DB::transaction(function () use ($match, $transaction, $invoice, $bankAccount) {
            if ($this->isDuplicatePayment($transaction, $invoice)) {
                throw new InvalidPaymentException('This payment has already been recorded for this invoice.');
            }

            $this->validatePreconditions($transaction, $bankAccount);
            $this->recordPaymentAndReconcile($transaction, $invoice, $bankAccount);

            $match->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            return $transaction->fresh(['journalEntry.lines', 'matchedInvoice', 'bankAccount']);
        });
    }

    /**
     * Record a payment for an invoice and mark the transaction as reconciled.
     */
    private function recordPaymentAndReconcile(
        BankTransaction $transaction,
        Invoice $invoice,
        BankAccount $bankAccount,
        ?string $bankAccountCode = null,
    ): void {
        $paymentAmount = $this->resolvePaymentAmount($transaction, $invoice);
        $orgId = $bankAccount->organization_id;

        $payment = null;
        if (bccomp($paymentAmount, '0', 2) > 0) {
            $payment = $this->invoiceAccountingService->recordPayment($invoice, new RecordPaymentData(
                amount: $paymentAmount,
                paymentDate: $transaction->date->toDateString(),
                paymentMethod: PaymentMethod::Bank,
                reference: $this->buildReference($orgId, $transaction),
                bankAccountCode: $bankAccountCode,
            ));
        }

        $this->bankingService->updateBankAccountBalance($bankAccount, $paymentAmount, true);

        $transaction->update([
            'journal_entry_id' => $payment?->journal_entry_id,
            'matched_invoice_id' => $invoice->id,
            'is_reconciled' => true,
        ]);
    }

    private function resolvePaymentAmount(BankTransaction $transaction, Invoice $invoice): string
    {
        $amount = Money::absoluteAmount((string) $transaction->amount);
        $amountDue = $invoice->amountDue();

        return bccomp($amount, $amountDue, 2) <= 0 ? $amount : $amountDue;
    }

    private function isDuplicatePayment(BankTransaction $transaction, Invoice $invoice): bool
    {
        if ($transaction->matched_invoice_id === $invoice->id) {
            return true;
        }

        if ($invoice->fresh()->isFullyPaid()) {
            return true;
        }

        return BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('invoice_id', $invoice->id)
            ->where('is_confirmed', true)
            ->exists();
    }
}
