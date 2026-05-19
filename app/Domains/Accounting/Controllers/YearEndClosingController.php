<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Requests\ReopenFiscalYearRequest;
use App\Domains\Accounting\Requests\StoreYearEndClosingRequest;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
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
        private readonly FiscalYearService $fiscalYears,
        private readonly YearEndClosingAction $closingAction,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('closeYear', Account::class);

        $orgId = $currentOrg->id();
        $org = Organization::findOrFail($orgId);

        $fiscalYears = FiscalYear::query()
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

        // Include any earlier years that already have journal entries (e.g. back-dated postings).
        // BelongsToOrganization global scope handles tenant filtering automatically in HTTP context.
        $earliestEntryYear = (int) JournalEntry::query()
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
            'unsettledVatPeriods' => $this->getUnsettledVatPeriods($year),
        ]);
    }

    public function store(StoreYearEndClosingRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('closeYear', Account::class);

        /** @var User $actingUser */
        $actingUser = $request->user();

        try {
            $nextYearCreated = $this->closingAction->execute(
                Organization::findOrFail($currentOrg->id()),
                $request->validated(),
                $actingUser,
            );
        } catch (\Throwable $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('accounting.closing')
            ->with('success', $nextYearCreated
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
                ->where('id', $fiscalYearId)
                ->first();
        }
        if ($fiscalYear === null) {
            $fiscalYear = FiscalYear::query()
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
    private function getUnsettledVatPeriods(int $year): array
    {
        $quarters = [
            1 => ["{$year}-01-01", "{$year}-03-31"],
            2 => ["{$year}-04-01", "{$year}-06-30"],
            3 => ["{$year}-07-01", "{$year}-09-30"],
            4 => ["{$year}-10-01", "{$year}-12-31"],
        ];

        $unsettled = [];

        foreach ($quarters as $q => [$from, $to]) {
            // Skip quarters that have no VAT-bearing activity at all.
            // JournalEntry's BelongsToOrganization global scope applies inside whereHas in HTTP context.
            $hasVatActivity = VatEntry::query()
                ->whereHas('journalEntry', fn ($jq) => $jq
                    ->whereBetween('date', [$from, $to])
                )
                ->exists();

            if (! $hasVatActivity) {
                continue;
            }

            $exists = JournalEntry::query()
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
