<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\Enums\FiscalYearChangeRequestStatus;
use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveFiscalYearChangeAction
{
    public function execute(FiscalYearChangeRequest $request, User $reviewer): FiscalYearChangeRequest
    {
        return DB::transaction(function () use ($request, $reviewer): FiscalYearChangeRequest {
            $lockedRequest = FiscalYearChangeRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw ValidationException::withMessages([
                    'fiscal_year_change' => [__('app.fiscal_year_change_not_pending')],
                ]);
            }

            $lockedRequest->update([
                'status' => FiscalYearChangeRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $lockedRequest->refresh();
        });
    }
}
