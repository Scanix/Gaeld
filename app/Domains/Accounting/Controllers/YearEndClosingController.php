<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\GenerateOpeningBalancesAction;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Requests\ReopenFiscalYearRequest;
use App\Domains\Accounting\Requests\StoreYearEndClosingRequest;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles fiscal year-end closing: generates closing entries and archives.
 */
class YearEndClosingController extends Controller
{
    use HandlesFlashErrorResponses;

    public function __construct(
        private readonly ClosingAccountsService $closingAccounts,
        private readonly LegalArchivingService $archiving,
        private readonly FiscalYearService $fiscalYears,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('closeYear', Account::class);

        $orgId = $currentOrg->id();
        $org = Organization::findOrFail($orgId);

        $fiscalYears = FiscalYear::query()
            ->where('organization_id', $orgId)
            ->orderBy('start_date', 'desc')
            ->get();

        $fiscalYearId = $request->string('fiscal_year_id')->toString();
        $selectedFiscalYear = $fiscalYearId !== ''
            ? $fiscalYears->firstWhere('id', $fiscalYearId)
            : null;

        $year = $request->integer('year', now()->year);

        if ($selectedFiscalYear !== null) {
            $year = (int) $selectedFiscalYear->start_date->year;
            $from = $selectedFiscalYear->start_date->toDateString();
            $to = $selectedFiscalYear->end_date->toDateString();
        } else {
            // Try to match a fiscal year record by the requested integer year.
            $matched = $fiscalYears->first(
                fn (FiscalYear $fy) => (int) $fy->start_date->year === $year
            );
            if ($matched !== null) {
                $selectedFiscalYear = $matched;
                $from = $matched->start_date->toDateString();
                $to = $matched->end_date->toDateString();
            } else {
                $from = "{$year}-01-01";
                $to = "{$year}-12-31";
            }
        }

        [$income, $expenses, $netResult] = $this->closingAccounts->compute($orgId, $from, $to);

        $startYear = $org->created_at ? $org->created_at->year : (now()->year - 5);

        // Include any earlier years that already have journal entries (e.g. back-dated postings)
        $earliestEntryYear = (int) JournalEntry::where('organization_id', $orgId)
            ->min(DB::raw('EXTRACT(YEAR FROM date)'));
        if ($earliestEntryYear > 0 && $earliestEntryYear < $startYear) {
            $startYear = $earliestEntryYear;
        }

        $availableYears = range($startYear, (int) now()->year);

        return Inertia::render('Accounting/YearEndClosing', [
            'year' => $year,
            'fiscalYearId' => $selectedFiscalYear?->id,
            'availableYears' => $availableYears,
            'fiscalYears' => $fiscalYears->map(fn (FiscalYear $fy) => [
                'id' => $fy->id,
                'name' => $fy->name,
                'start_date' => $fy->start_date->toDateString(),
                'end_date' => $fy->end_date->toDateString(),
                'status' => $fy->status->value,
            ])->values(),
            'fromDate' => $from,
            'toDate' => $to,
            'income' => $income,
            'expenses' => $expenses,
            'netResult' => $netResult,
            'closedYears' => $org->closed_fiscal_years ?? [],
            'canReopenYear' => $request->user()?->can('reopenYear', Account::class) ?? false,
            'unsettledVatPeriods' => $this->getUnsettledVatPeriods($orgId, $year),
        ]);
    }

    public function store(StoreYearEndClosingRequest $request, CurrentOrganization $currentOrg, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('closeYear', Account::class);

        $orgId = $currentOrg->id();

        $validated = $request->validated();
        $year = (int) $validated['year'];

        // Resolve the FiscalYear record (preferred) or fall back to calendar year.
        $fiscalYearId = $validated['fiscal_year_id'] ?? null;
        $fiscalYear = null;
        if ($fiscalYearId !== null) {
            $fiscalYear = FiscalYear::query()
                ->where('organization_id', $orgId)
                ->where('id', $fiscalYearId)
                ->first();
        }
        if ($fiscalYear === null) {
            $fiscalYear = FiscalYear::query()
                ->where('organization_id', $orgId)
                ->whereYear('start_date', $year)
                ->first();
        }

        if ($fiscalYear !== null) {
            $year = (int) $fiscalYear->start_date->year;
            $from = $fiscalYear->start_date->toDateString();
            $to = $fiscalYear->end_date->toDateString();
        } else {
            $from = "{$year}-01-01";
            $to = "{$year}-12-31";
        }

        [$income, $expenses] = $this->closingAccounts->compute($orgId, $from, $to);

        $allAccounts = array_merge($income, $expenses);

        if (empty($allAccounts)) {
            return $this->backWithError('No accounts to close for this period.');
        }

        // Hard block: require all VAT periods to be settled before closing
        $unsettled = $this->getUnsettledVatPeriods($orgId, $year);
        if (! empty($unsettled)) {
            return $this->backWithError(
                __('app.fiscal_year_unsettled_vat', [
                    'year' => $year,
                    'periods' => implode(', ', $unsettled),
                ])
            );
        }

        // Find the result account
        $resultAccount = Account::where('organization_id', $orgId)
            ->where('code', $validated['result_account_code'])
            ->first();

        if (! $resultAccount) {
            return redirect()->back()->withErrors([
                'result_account_code' => "Account '{$validated['result_account_code']}' not found.",
            ]);
        }

        try {
            DB::transaction(function () use ($income, $expenses, $year, $validated, $resultAccount, $orgId, $ledger) {
                // Build closing journal lines
                // Revenue (credit-normal): debit the account, credit result
                // Expense (debit-normal):  credit the account, debit result
                $lines = [];
                $netDebitOnResult = '0';  // debits to result account (for expenses)
                $netCreditOnResult = '0';  // credits to result account (for revenues)

                foreach ($income as $row) {
                    if (Money::isZero((string) $row['balance'])) {
                        continue;
                    }
                    $lines[] = new JournalLineData(
                        accountId: (string) $row['account_id'],
                        debit: (string) $row['balance'],
                        credit: '0',
                        description: __('app.closing_line_description', ['year' => $year, 'code' => $row['code']]),
                    );
                    $netCreditOnResult = Money::add($netCreditOnResult, (string) $row['balance']);
                }

                foreach ($expenses as $row) {
                    if (Money::isZero((string) $row['balance'])) {
                        continue;
                    }
                    $lines[] = new JournalLineData(
                        accountId: (string) $row['account_id'],
                        debit: '0',
                        credit: (string) $row['balance'],
                        description: __('app.closing_line_description', ['year' => $year, 'code' => $row['code']]),
                    );
                    $netDebitOnResult = Money::add($netDebitOnResult, (string) $row['balance']);
                }

                // Add result account line (net debit or credit)
                $netDebit = $netDebitOnResult;
                $netCredit = $netCreditOnResult;

                $lines[] = new JournalLineData(
                    accountId: (string) $resultAccount->id,
                    debit: $netDebit,
                    credit: $netCredit,
                    description: __('app.closing_result_description', ['year' => $year]),
                );

                $entry = new JournalEntryData(
                    date: $validated['closing_date'],
                    reference: $validated['reference'],
                    description: __('app.closing_entry_description', ['year' => $year]),
                    lines: $lines,
                );

                $journalEntry = $ledger->postEntry($orgId, $entry);

                $journalEntry->update(['type' => 'year_end_closing']);
            });

            // Lock the fiscal year to prevent further postings
            $org = Organization::findOrFail($orgId);
            $org->closeFiscalYear($year);

            if ($fiscalYear !== null) {
                $nextYearCreated = $this->fiscalYears->close($fiscalYear, $request->user());
            }

            // Archive all documents for the closed fiscal year (Swiss OR 10-year retention)
            $this->archiving->archiveFiscalYear($orgId, $year);

            // Generate opening balance entries for the next fiscal year
            app(GenerateOpeningBalancesAction::class)->execute($orgId, $year);
        } catch (\Throwable $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('accounting.closing')
            ->with('success', ($nextYearCreated ?? false)
                ? __('app.year_end_closing_done_next_created')
                : __('app.year_end_closing_done')
            );
    }

    public function reopen(ReopenFiscalYearRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('reopenYear', Account::class);

        $validated = $request->validated();

        $year = (int) $validated['year'];
        $org = Organization::findOrFail($currentOrg->id());

        $fiscalYearId = $validated['fiscal_year_id'] ?? null;
        $fiscalYear = null;
        if ($fiscalYearId !== null) {
            $fiscalYear = FiscalYear::query()
                ->where('organization_id', $org->id)
                ->where('id', $fiscalYearId)
                ->first();
        }
        if ($fiscalYear === null) {
            $fiscalYear = FiscalYear::query()
                ->where('organization_id', $org->id)
                ->where('status', FiscalYearStatus::Closed->value)
                ->whereYear('start_date', $year)
                ->first();
        }

        $isClosed = $fiscalYear?->isClosed() ?? $org->isFiscalYearClosed($year);

        if (! $isClosed) {
            return $this->backWithError(__('app.fiscal_year_not_closed', ['year' => $year]));
        }

        $org->reopenFiscalYear($year);

        if ($fiscalYear !== null) {
            $this->fiscalYears->reopen($fiscalYear);
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($org)
            ->withProperties(['year' => $year])
            ->log("Fiscal year {$year} reopened");

        return redirect()->route('accounting.closing', ['year' => $year])
            ->with('success', __('app.fiscal_year_reopened', ['year' => $year]));
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Return quarter labels (e.g. "Q1", "Q2") for which no VAT settlement
     * journal entry exists in the given year.
     *
     * @return string[]
     */
    private function getUnsettledVatPeriods(string $orgId, int $year): array
    {
        $quarters = [
            1 => ["{$year}-01-01", "{$year}-03-31"],
            2 => ["{$year}-04-01", "{$year}-06-30"],
            3 => ["{$year}-07-01", "{$year}-09-30"],
            4 => ["{$year}-10-01", "{$year}-12-31"],
        ];

        $unsettled = [];

        foreach ($quarters as $q => [$from, $to]) {
            // Skip quarters that have no VAT-bearing activity at all
            $hasVatActivity = VatEntry::query()
                ->whereHas('journalEntry', fn ($jq) => $jq
                    ->where('organization_id', $orgId)
                    ->whereBetween('date', [$from, $to])
                )
                ->exists();

            if (! $hasVatActivity) {
                continue;
            }

            $exists = JournalEntry::where('organization_id', $orgId)
                ->where('type', 'vat_settlement')
                ->where('reference', "VAT-SETTLEMENT-{$from}-{$to}")
                ->exists();

            if (! $exists) {
                $unsettled[] = "Q{$q}";
            }
        }

        return $unsettled;
    }
}
