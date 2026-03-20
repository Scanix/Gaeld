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
use App\Support\DTOs\SummaryResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post the ledger entry for an invoice.
     *
     * Accounting effect:
     *   Debit  1100 Accounts Receivable  (invoice total)
     *   Credit 3000 Revenue from Services (invoice total)
     *
     * Marks the invoice as Sent.
     */
    public function postToLedger(Invoice $invoice): Invoice
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
        $bankAccountCode = $data->bankAccountCode ?? AccountCode::BANK_CASH;

        return DB::transaction(function () use ($invoice, $data, $bankAccountCode) {
            $orgId = $invoice->organization_id;

            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);
            $accountsReceivable = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);

            $paymentRef = $data->reference ?? 'PAY-' . $invoice->number . '-' . ($invoice->payments()->count() + 1);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $data->paymentDate,
                reference: $paymentRef,
                description: "Payment received for {$invoice->number}",
                lines: [
                    new JournalLineData(accountId: $bankAccount->id, debit: $data->amount, credit: '0', description: 'Bank deposit'),
                    new JournalLineData(accountId: $accountsReceivable->id, debit: '0', credit: $data->amount, description: 'Clear receivable'),
                ],
            ));

            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $data->amount,
                'payment_date' => $data->paymentDate,
                'payment_method' => $data->paymentMethod->value,
                'reference' => $paymentRef,
            ]);

            // Check if invoice is fully paid
            if ($invoice->fresh()->isFullyPaid()) {
                $invoice->update(['status' => InvoiceStatus::Paid]);
            }

            return $payment->load('journalEntry');
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  Reporting queries
    // ──────────────────────────────────────────────────────────────

    public function yearlyRevenue(string $orgId, int $year): string
    {
        return (string) Invoice::where('organization_id', $orgId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->sum('total');
    }

    public function unpaidSummary(string $orgId): SummaryResult
    {
        $row = Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        return new SummaryResult(
            count: (int) ($row->count ?? 0),
            total: (string) ($row->total ?? '0'),
        );
    }

    public function paidInYear(string $orgId, int $year): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->select('number', 'total', 'issue_date')
            ->get();
    }

    public function sentOrOverdueDueInYear(string $orgId, int $year): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereYear('due_date', $year)
            ->select('number', 'total', 'due_date')
            ->get();
    }

    public function hasMatchingQrReference(string $organizationId, string $reference): bool
    {
        return Invoice::where('organization_id', $organizationId)
            ->where('qr_reference', $reference)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->exists();
    }
}
