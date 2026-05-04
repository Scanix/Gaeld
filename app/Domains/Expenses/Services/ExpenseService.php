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
     * Convention: $expense->amount is the NET amount (excl. VAT).
     * Gross (paid) = amount + vat_amount.
     *
     * For regular expenses (with VAT):
     *   Debit  {expenseAccountCode} Expense account  (NET amount)
     *   Debit  1170                  Input VAT        (vat_amount)
     *   Credit {bankAccountCode}    Bank account     (GROSS = NET + VAT)
     *
     * For credit notes (reversed): mirror of the above.
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

            $netAmount = $data->amount;
            $vatAmount = (string) ($expense->vat_amount ?? '0');
            $hasVat = $expense->vat_rate_id && Money::isPositive($vatAmount);
            $grossAmount = $hasVat ? Money::add($netAmount, $vatAmount) : $netAmount;

            if ($isCreditNote) {
                // Credit note: reverse the normal flow
                $lines = [
                    new JournalLineData(accountId: (string) $bankAccount->id, debit: $grossAmount, credit: '0', description: 'Supplier credit note — bank refund'),
                    new JournalLineData(accountId: (string) $expenseAccount->id, debit: '0', credit: $netAmount, description: $expense->description ?? 'Credit note'),
                ];
                if ($hasVat) {
                    $vatAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::VAT_INPUT);
                    $lines[] = new JournalLineData(
                        accountId: (string) $vatAccount->id,
                        debit: '0',
                        credit: $vatAmount,
                        description: 'Input VAT reversal',
                    );
                }
            } else {
                $lines = [
                    new JournalLineData(accountId: (string) $expenseAccount->id, debit: $netAmount, credit: '0', description: $expense->description ?? 'Expense'),
                ];
                if ($hasVat) {
                    $vatAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::VAT_INPUT);
                    $lines[] = new JournalLineData(
                        accountId: (string) $vatAccount->id,
                        debit: $vatAmount,
                        credit: '0',
                        description: 'Input VAT',
                    );
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $bankAccount->id,
                    debit: '0',
                    credit: $grossAmount,
                    description: 'Bank withdrawal',
                );
            }

            // Apply Swiss 5-centime rounding for CHF expenses (regular invoices only),
            // adjusting the bank credit (gross) and posting the rounding difference.
            if (! $isCreditNote && strtoupper($expense->currency) === 'CHF') {
                $adj = SwissRounding::adjustment(Money::of($grossAmount));

                if ($adj) {
                    $roundingAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::ROUNDING_DIFFERENCE);

                    // Replace the bank credit line (always the last entry above) with the rounded gross
                    $bankLineIndex = array_key_last($lines);
                    $lines[$bankLineIndex] = new JournalLineData(
                        accountId: (string) $bankAccount->id,
                        debit: '0',
                        credit: $adj['rounded'],
                        description: 'Bank withdrawal',
                    );

                    $absDiff = Money::absoluteAmount($adj['diff']);
                    $lines[] = new JournalLineData(
                        accountId: (string) $roundingAccount->id,
                        debit: Money::isPositive($adj['diff']) ? $absDiff : '0',
                        credit: Money::isNegative($adj['diff']) ? $absDiff : '0',
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

            // Create VatEntry record for the VAT report (Input VAT).
            // base_amount is NET (= expense->amount under our convention).
            if ($hasVat) {
                VatEntry::create([
                    'journal_entry_id' => $journalEntry->id,
                    'vat_rate_id' => $expense->vat_rate_id,
                    'base_amount' => (string) $expense->amount,
                    'vat_amount' => $vatAmount,
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

    /**
     * @return Collection<int, Expense>
     */
    public function inYear(string $orgId, int $year): Collection
    {
        return Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->select('description', 'amount', 'date')
            ->get();
    }
}
