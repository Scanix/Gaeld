<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\FiscalYearData;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Exceptions\FiscalYearOverlapException;
use App\Domains\Accounting\Exceptions\FiscalYearTooLongException;
use App\Domains\Accounting\Exceptions\InvalidFiscalYearRangeException;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Domain service for fiscal year lifecycle management.
 *
 * Handles creation, validation (overlap + duration), state transitions
 * (planned → operative → expired → closed), and date-based lookups.
 */
class FiscalYearService
{
    /** Swiss legal maximum for a long first fiscal year. */
    public const MAX_DURATION_MONTHS = 23;

    /** The currently operative fiscal year, if any. */
    public function getOperative(Organization $organization): ?FiscalYear
    {
        return FiscalYear::query()
            ->where('organization_id', $organization->id)
            ->operative()
            ->orderBy('start_date')
            ->first();
    }

    /** Find the fiscal year containing the given ISO date (YYYY-MM-DD). */
    public function getFiscalYearForDate(Organization $organization, string $date): ?FiscalYear
    {
        return FiscalYear::query()
            ->where('organization_id', $organization->id)
            ->forDate($date)
            ->first();
    }

    /**
     * Create a new fiscal year, validating range, duration, and overlap.
     *
     * @throws InvalidFiscalYearRangeException
     * @throws FiscalYearTooLongException
     * @throws FiscalYearOverlapException
     */
    public function create(Organization $organization, FiscalYearData $data): FiscalYear
    {
        $start = Carbon::parse($data->startDate)->startOfDay();
        $end = Carbon::parse($data->endDate)->startOfDay();

        $this->validateRange($start, $end);
        $this->validateNoOverlap($organization, $start, $end);

        return FiscalYear::create([
            'organization_id' => $organization->id,
            'name' => $data->name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => $this->initialStatusFor($start, $end),
        ]);
    }

    /**
     * Update name and/or dates of a fiscal year.
     *
     * Closed years cannot be edited; operative years can have their name
     * updated but not their dates.
     *
     * @throws InvalidFiscalYearRangeException
     * @throws FiscalYearTooLongException
     * @throws FiscalYearOverlapException
     */
    public function update(FiscalYear $fiscalYear, FiscalYearData $data): FiscalYear
    {
        if ($fiscalYear->isClosed()) {
            throw new \DomainException(__('app.fiscal_year_closed_cannot_edit'));
        }

        $start = Carbon::parse($data->startDate)->startOfDay();
        $end = Carbon::parse($data->endDate)->startOfDay();

        $datesChanged = ! $start->equalTo($fiscalYear->start_date->startOfDay())
            || ! $end->equalTo($fiscalYear->end_date->startOfDay());

        if ($datesChanged && $fiscalYear->isOperative()) {
            throw new \DomainException(__('app.fiscal_year_operative_dates_locked'));
        }

        if ($datesChanged) {
            $this->validateRange($start, $end);
            $this->validateNoOverlap(
                $fiscalYear->organization,
                $start,
                $end,
                excludeId: $fiscalYear->id,
            );
        }

        $fiscalYear->update([
            'name' => $data->name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);

        return $fiscalYear->refresh();
    }

    /**
     * Mark the fiscal year as closed, lock it, and auto-advance the next
     * planned year (if any) to operative.
     */
    public function close(FiscalYear $fiscalYear, User $user): void
    {
        DB::transaction(function () use ($fiscalYear, $user) {
            $fiscalYear->update([
                'status' => FiscalYearStatus::Closed,
                'locked_at' => now(),
                'locked_by_user_id' => $user->id,
            ]);

            $next = FiscalYear::query()
                ->where('organization_id', $fiscalYear->organization_id)
                ->where('status', FiscalYearStatus::Planned->value)
                ->whereDate('start_date', '>', $fiscalYear->end_date->toDateString())
                ->orderBy('start_date')
                ->first();

            if ($next) {
                $next->update(['status' => FiscalYearStatus::Operative]);
            }
        });
    }

    /**
     * Reopen a closed fiscal year. The status becomes 'expired' (since the
     * end_date has, by definition, passed by the time it was closed).
     */
    public function reopen(FiscalYear $fiscalYear): void
    {
        $fiscalYear->update([
            'status' => FiscalYearStatus::Expired,
            'locked_at' => null,
            'locked_by_user_id' => null,
        ]);
    }

    /**
     * Transition any operative year whose end_date has passed to 'expired'.
     * Designed to be called from a scheduled command.
     */
    public function markExpired(Organization $organization): int
    {
        return FiscalYear::query()
            ->where('organization_id', $organization->id)
            ->where('status', FiscalYearStatus::Operative->value)
            ->whereDate('end_date', '<', Carbon::today()->toDateString())
            ->update(['status' => FiscalYearStatus::Expired->value]);
    }

    /** @throws InvalidFiscalYearRangeException|FiscalYearTooLongException */
    private function validateRange(Carbon $start, Carbon $end): void
    {
        if ($end->lte($start)) {
            throw new InvalidFiscalYearRangeException;
        }

        $months = (int) $start->diffInMonths($end->copy()->addDay());
        if ($months > self::MAX_DURATION_MONTHS) {
            throw new FiscalYearTooLongException($months);
        }
    }

    /** @throws FiscalYearOverlapException */
    private function validateNoOverlap(
        Organization $organization,
        Carbon $start,
        Carbon $end,
        ?string $excludeId = null,
    ): void {
        $query = FiscalYear::query()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new FiscalYearOverlapException(
                $start->toDateString(),
                $end->toDateString(),
            );
        }
    }

    private function initialStatusFor(Carbon $start, Carbon $end): FiscalYearStatus
    {
        $today = Carbon::today();

        return match (true) {
            $end->lt($today) => FiscalYearStatus::Expired,
            $start->gt($today) => FiscalYearStatus::Planned,
            default => FiscalYearStatus::Operative,
        };
    }
}
