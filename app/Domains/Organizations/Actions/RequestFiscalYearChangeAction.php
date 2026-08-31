<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\Enums\FiscalYearChangeRequestStatus;
use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestFiscalYearChangeAction
{
    public function execute(
        Organization $organization,
        User $requester,
        string $requestedStart,
        ?string $reason = null,
    ): FiscalYearChangeRequest {
        $currentStart = (string) ($organization->fiscal_year_start ?: '01-01');

        if (! $this->isValidMonthDay($requestedStart)) {
            throw ValidationException::withMessages([
                'requested_start' => [__('app.fiscal_year_change_invalid_start')],
            ]);
        }

        if ($requestedStart === $currentStart) {
            throw ValidationException::withMessages([
                'requested_start' => [__('app.fiscal_year_change_same_start')],
            ]);
        }

        $now = now();
        $effectiveYear = $now->format('m-d') < $requestedStart ? $now->year : $now->year + 1;

        $data = [
            'organization_id' => $organization->id,
            'requested_by_user_id' => $requester->id,
            'current_start' => $currentStart,
            'requested_start' => $requestedStart,
            'effective_year' => $effectiveYear,
            'status' => FiscalYearChangeRequestStatus::Pending,
            'reason' => $reason,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];

        return DB::transaction(function () use ($data, $organization): FiscalYearChangeRequest {
            $pending = FiscalYearChangeRequest::query()
                ->where('organization_id', $organization->id)
                ->where('status', FiscalYearChangeRequestStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($pending) {
                $pending->update($data);

                return $pending->refresh();
            }

            return FiscalYearChangeRequest::create($data);
        });
    }

    private function isValidMonthDay(string $monthDay): bool
    {
        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $monthDay) !== 1) {
            return false;
        }

        [$month, $day] = array_map('intval', explode('-', $monthDay));

        return Carbon::create(2000, $month, $day)->format('m-d') === $monthDay;
    }
}
