<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Support\Money;
use App\Support\SwissRounding;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Handles write operations for invoice accounting: posting ledger entries
 * and recording payments. Coordinates with LedgerService and applies
 * Swiss rounding to totals.
 */
class InvoiceAccountingService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post the ledger entry for an invoice.
     *
     * Accounting effect (multi-VAT aware):
     *   Debit  1100 Accounts Receivable  (invoice total incl. VAT)
     *   Credit 3000 Revenue from Services (net amount per VAT group)
     *   Credit 2200 VAT Output Tax        (VAT amount per VAT group)
     *
     * For credit notes, debit/credit are reversed:
     *   Credit 1100 Accounts Receivable
     *   Debit  3000 Revenue from Services
     *   Debit  2200 VAT Output Tax
     *
     * Marks the invoice as Sent.
     */
    public function postToLedger(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $orgId = $invoice->organization_id;
            $invoice->load('lines.vatRate');

            $isCreditNote = $invoice->type === InvoiceType::CreditNote;

            $ar = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);
            $revenue = $this->ledgerService->resolveAccount($orgId, AccountCode::REVENUE);

            $lines = [];

            // For credit notes, amounts are negative — use absolute values and swap debit/credit
            $invoiceTotal = $isCreditNote
                ? Money::absoluteAmount((string) $invoice->total)
                : (string) $invoice->total;

            // AR line: Debit for invoice, Credit for credit note
            $lines[] = new JournalLineData(
                accountId: (string) $ar->id,
                debit: $isCreditNote ? '0' : $invoiceTotal,
                credit: $isCreditNote ? $invoiceTotal : '0',
                description: 'Accounts Receivable',
            );

            // Group invoice lines by VAT rate to create separate revenue + VAT entries
            $groupedByVat = $invoice->lines->groupBy(fn ($line) => $line->vat_rate_id ?? 'none');

            foreach ($groupedByVat as $vatRateId => $invoiceLines) {
                $netAmount = '0';
                $vatAmount = '0';

                foreach ($invoiceLines as $line) {
                    $netAmount = bcadd($netAmount, Money::absoluteAmount((string) $line->amount), 2);
                    $vatAmount = bcadd($vatAmount, Money::absoluteAmount((string) ($line->vat_amount ?? '0')), 2);
                }

                // Revenue line: Credit for invoice, Debit for credit note
                if (bccomp($netAmount, '0', 2) > 0) {
                    $vatLabel = $vatRateId !== 'none' && $invoiceLines->first()->vatRate
                        ? " ({$invoiceLines->first()->vatRate->name})"
                        : '';
                    $lines[] = new JournalLineData(
                        accountId: (string) $revenue->id,
                        debit: $isCreditNote ? $netAmount : '0',
                        credit: $isCreditNote ? '0' : $netAmount,
                        description: "Revenue{$vatLabel}",
                    );
                }

                // VAT line: Credit for invoice, Debit for credit note
                if (bccomp($vatAmount, '0', 2) > 0) {
                    $vatOutputAccount = $this->ledgerService->resolveAccount($orgId, AccountCode::VAT_OUTPUT);
                    $rateName = $invoiceLines->first()->vatRate?->name ?? 'VAT';
                    $lines[] = new JournalLineData(
                        accountId: (string) $vatOutputAccount->id,
                        debit: $isCreditNote ? $vatAmount : '0',
                        credit: $isCreditNote ? '0' : $vatAmount,
                        description: "VAT Output — {$rateName}",
                    );
                }
            }

            // Apply Swiss 5-centime rounding for CHF invoices
            if (strtoupper($invoice->currency) === 'CHF') {
                $originalTotal = $isCreditNote
                    ? Money::absoluteAmount((string) $invoice->total)
                    : (string) $invoice->total;
                $roundedTotal = SwissRounding::roundToFiveCents($originalTotal);
                $roundingDiff = SwissRounding::difference($originalTotal, $roundedTotal);

                if (bccomp($roundingDiff, '0', 2) !== 0) {
                    $roundingAccount = $this->ledgerService->resolveAccount($orgId, AccountCode::ROUNDING_DIFFERENCE);

                    // Adjust the AR line to the rounded total
                    $lines[0] = new JournalLineData(
                        accountId: (string) $ar->id,
                        debit: $isCreditNote ? '0' : $roundedTotal,
                        credit: $isCreditNote ? $roundedTotal : '0',
                        description: 'Accounts Receivable',
                    );

                    // Post the rounding difference to keep the entry balanced
                    if (bccomp($roundingDiff, '0', 2) < 0) {
                        $lines[] = new JournalLineData(
                            accountId: (string) $roundingAccount->id,
                            debit: $isCreditNote ? '0' : Money::absoluteAmount($roundingDiff),
                            credit: $isCreditNote ? Money::absoluteAmount($roundingDiff) : '0',
                            description: 'Rounding difference (5ct)',
                        );
                    } else {
                        $lines[] = new JournalLineData(
                            accountId: (string) $roundingAccount->id,
                            debit: $isCreditNote ? $roundingDiff : '0',
                            credit: $isCreditNote ? '0' : $roundingDiff,
                            description: 'Rounding difference (5ct)',
                        );
                    }
                }
            }

            $docType = $isCreditNote ? 'Credit Note' : 'Invoice';
            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $invoice->issue_date->toDateString(),
                reference: $invoice->number,
                description: "{$docType} {$invoice->number} — ".($invoice->customer?->name ?? 'N/A'),
                lines: $lines,
            ));

            // Create VatEntry records for the VAT report
            foreach ($groupedByVat as $vatRateId => $invoiceLines) {
                if ($vatRateId === 'none') {
                    continue;
                }

                $netAmount = '0';
                $vatAmount = '0';
                foreach ($invoiceLines as $line) {
                    $netAmount = bcadd($netAmount, Money::absoluteAmount((string) $line->amount), 2);
                    $vatAmount = bcadd($vatAmount, Money::absoluteAmount((string) ($line->vat_amount ?? '0')), 2);
                }

                if (bccomp($vatAmount, '0', 2) > 0) {
                    VatEntry::create([
                        'journal_entry_id' => $journalEntry->id,
                        'vat_rate_id' => $vatRateId,
                        'base_amount' => $netAmount,
                        'vat_amount' => $vatAmount,
                        'type' => VatEntryType::Output,
                    ]);
                }
            }

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
     * @throws ModelNotFoundException When account code not found
     */
    public function recordPayment(Invoice $invoice, RecordPaymentData $data): InvoicePayment
    {
        $bankAccountCode = $data->bankAccountCode ?? AccountCode::BANK_CASH;

        return DB::transaction(function () use ($invoice, $data, $bankAccountCode) {
            $orgId = $invoice->organization_id;

            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);
            $accountsReceivable = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);

            $paymentRef = $data->reference ?? 'PAY-'.$invoice->number.'-'.($invoice->payments()->count() + 1);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $data->paymentDate,
                reference: $paymentRef,
                description: "Payment received for {$invoice->number}",
                lines: [
                    new JournalLineData(accountId: (string) $bankAccount->id, debit: $data->amount, credit: '0', description: 'Bank deposit'),
                    new JournalLineData(accountId: (string) $accountsReceivable->id, debit: '0', credit: $data->amount, description: 'Clear receivable'),
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
}
