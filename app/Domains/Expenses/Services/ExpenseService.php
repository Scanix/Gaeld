<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Support\DTOs\SummaryResult;
use App\Support\Money;
use App\Support\SwissRounding;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for creating, updating, approving, and paying expenses.
 *
 * Handles VAT calculations, Swiss rounding, journal entry posting,
 * and expense status transitions.
 */
class ExpenseService
{
    public function __construct(
        private LedgerService $ledgerService,
        private LedgerQueryService $ledgerQuery,
    ) {}

    /**
     * Post the ledger entry for an expense payment.
     *
     * For regular expenses:
     *   Debit  {expenseAccountCode} Expense account  (payment amount)
     *   Credit {bankAccountCode}    Bank account     (payment amount)
     *
     * For credit notes (reversed):
     *   Debit  {bankAccountCode}    Bank account     (refund amount)
     *   Credit {expenseAccountCode} Expense account  (refund amount)
     *
     * Marks the expense as Posted.
     *
     * @throws ModelNotFoundException When account code not found
     */
    public function postToLedger(Expense $expense, RecordExpensePaymentData $data, bool $isCreditNote = false): JournalEntry
    {
        return DB::transaction(function () use ($expense, $data, $isCreditNote) {
            $orgId = $expense->organization_id;
            $bankAccountCode = $data->bankAccountCode ?? AccountCode::BANK_CASH;

            $expenseAccount = $this->ledgerQuery->resolveAccount($orgId, $data->expenseAccountCode);
            $bankAccount = $this->ledgerQuery->resolveAccount($orgId, $bankAccountCode);

            $amount = $data->amount;

            if ($isCreditNote) {
                // Credit note: reverse the normal flow (bank receives, expense account credited)
                $lines = [
                    new JournalLineData(accountId: (string) $bankAccount->id, debit: $amount, credit: '0', description: 'Supplier credit note — bank refund'),
                    new JournalLineData(accountId: (string) $expenseAccount->id, debit: '0', credit: $amount, description: $expense->description ?? 'Credit note'),
                ];
            } else {
                $lines = [
                    new JournalLineData(accountId: (string) $expenseAccount->id, debit: $amount, credit: '0', description: $expense->description ?? 'Expense'),
                    new JournalLineData(accountId: (string) $bankAccount->id, debit: '0', credit: $amount, description: 'Bank withdrawal'),
                ];
            }

            // Apply Swiss 5-centime rounding for CHF expenses (regular invoices only)
            if (! $isCreditNote && strtoupper($expense->currency) === 'CHF') {
                $adj = SwissRounding::adjustment($amount);

                if ($adj) {
                    $roundingAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::ROUNDING_DIFFERENCE);

                    // Adjust the bank credit to the rounded amount
                    $lines = [
                        new JournalLineData(accountId: (string) $expenseAccount->id, debit: $amount, credit: '0', description: $expense->description ?? 'Expense'),
                        new JournalLineData(accountId: (string) $bankAccount->id, debit: '0', credit: $adj['rounded'], description: 'Bank withdrawal'),
                    ];

                    $absDiff = Money::absoluteAmount($adj['diff']);
                    $lines[] = new JournalLineData(
                        accountId: (string) $roundingAccount->id,
                        debit: bccomp($adj['diff'], '0', 2) > 0 ? $absDiff : '0',
                        credit: bccomp($adj['diff'], '0', 2) < 0 ? $absDiff : '0',
                        description: 'Rounding difference (5ct)',
                    );
                }
            }

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $data->paymentDate,
                reference: $data->reference,
                description: $data->description,
                lines: $lines,
            ));

            // Create VatEntry record for the VAT report (Input VAT)
            if ($expense->vat_rate_id && bccomp((string) $expense->vat_amount, '0', 2) > 0) {
                VatEntry::create([
                    'journal_entry_id' => $journalEntry->id,
                    'vat_rate_id' => $expense->vat_rate_id,
                    'base_amount' => bcsub((string) $expense->amount, (string) $expense->vat_amount, 2),
                    'vat_amount' => (string) $expense->vat_amount,
                    'type' => VatEntryType::Input,
                ]);
            }

            $expense->update([
                'status' => ExpenseStatus::Posted,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $journalEntry;
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  Reporting queries
    // ──────────────────────────────────────────────────────────────

    public function yearlyTotal(string $orgId, int $year): string
    {
        $total = Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->sum('amount');

        return $total ? (string) $total : '0.00';
    }

    public function pendingSummary(string $orgId): SummaryResult
    {
        $row = Expense::where('organization_id', $orgId)
            ->where('status', ExpenseStatus::Pending)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return new SummaryResult(
            count: (int) ($row->count ?? 0),
            total: (string) ($row->total ?? '0'),
        );
    }

    public function inYear(string $orgId, int $year): Collection
    {
        return Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->select('description', 'amount', 'date')
            ->get();
    }
}
