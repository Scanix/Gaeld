<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\Enums\FiscalYearChangeRequestStatus;
use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplyFiscalYearChangeAction
{
    public function execute(
        FiscalYearChangeRequest $request,
        Organization $organization,
    ): FiscalYearChangeRequest {
        if ((string) $request->organization_id !== (string) $organization->id) {
            abort(404);
        }

        return DB::transaction(function () use ($request, $organization): FiscalYearChangeRequest {
            $lockedRequest = FiscalYearChangeRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== FiscalYearChangeRequestStatus::Approved) {
                throw ValidationException::withMessages([
                    'fiscal_year_change' => [__('app.fiscal_year_change_not_approved')],
                ]);
            }

            $effectiveDate = $this->effectiveDate($lockedRequest);
            if (Carbon::today()->lt($effectiveDate)) {
                throw ValidationException::withMessages([
                    'fiscal_year_change' => [__('app.fiscal_year_change_not_yet_effective')],
                ]);
            }

            $organization->update([
                'fiscal_year_start' => $lockedRequest->requested_start,
            ]);
            $lockedRequest->update([
                'status' => FiscalYearChangeRequestStatus::Applied,
            ]);

            return $lockedRequest->refresh();
        });
    }

    private function effectiveDate(FiscalYearChangeRequest $request): Carbon
    {
        [$month, $day] = array_map('intval', explode('-', $request->requested_start));

        return Carbon::create((int) $request->effective_year, $month, $day)->startOfDay();
    }
}
