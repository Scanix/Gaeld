<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Exceptions\DomainException;

/**
 * Reopens a previously closed fiscal year: validates the year is actually
 * closed, clears the closed-year flag on the organisation, transitions the
 * matching FiscalYear record back to Active, and records an audit trail
 * entry. Reopening does not undo the closing journal entry or archive — it
 * only lifts the write lock so corrections can be posted.
 */
class ReopenFiscalYearAction
{
    public function __construct(
        private readonly FiscalYearService $fiscalYears,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  Validated fields from ReopenFiscalYearRequest
     *
     * @throws DomainException when the requested year is not currently closed
     */
    public function execute(Organization $org, array $validated, User $actingUser): void
    {
        $year = (int) $validated['year'];
        $fiscalYearId = $validated['fiscal_year_id'] ?? null;

        $fiscalYear = $this->resolveFiscalYear($org, $year, $fiscalYearId);

        $isClosed = $fiscalYear?->isClosed() ?? $org->isFiscalYearClosed($year);

        if (! $isClosed) {
            throw new DomainException(__('app.fiscal_year_not_closed', ['year' => $year]));
        }

        $org->reopenFiscalYear($year);

        if ($fiscalYear !== null) {
            $this->fiscalYears->reopen($fiscalYear);
        }

        activity()
            ->causedBy($actingUser)
            ->performedOn($org)
            ->withProperties(['year' => $year])
            ->log("Fiscal year {$year} reopened");
    }

    private function resolveFiscalYear(Organization $org, int $year, ?string $fiscalYearId): ?FiscalYear
    {
        if ($fiscalYearId !== null) {
            $fiscalYear = FiscalYear::query()->where('id', $fiscalYearId)->first();
            if ($fiscalYear !== null) {
                return $fiscalYear;
            }
        }

        return FiscalYear::query()
            ->where('status', FiscalYearStatus::Closed->value)
            ->whereYear('start_date', $year)
            ->first();
    }
}
