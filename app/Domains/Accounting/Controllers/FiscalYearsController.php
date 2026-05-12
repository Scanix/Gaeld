<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\DTOs\FiscalYearData;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Requests\StoreFiscalYearRequest;
use App\Domains\Accounting\Requests\UpdateFiscalYearRequest;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for fiscal year records.
 *
 * Authorization piggybacks on existing `closeYear` permission (granted to
 * owners and accountants) since managing fiscal years is a sensitive
 * accounting operation.
 */
class FiscalYearsController extends Controller
{
    use HandlesFlashErrorResponses;

    public function __construct(
        private readonly FiscalYearService $fiscalYears,
    ) {}

    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('closeYear', Account::class);

        $orgId = $currentOrg->id();

        $fiscalYears = FiscalYear::query()
            ->where('organization_id', $orgId)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (FiscalYear $fy) => [
                'id' => $fy->id,
                'name' => $fy->name,
                'start_date' => $fy->start_date->toDateString(),
                'end_date' => $fy->end_date->toDateString(),
                'status' => $fy->status->value,
                'duration_months' => $fy->durationInMonths(),
                'locked_at' => $fy->locked_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Accounting/FiscalYears/Index', [
            'fiscalYears' => $fiscalYears,
            'maxDurationMonths' => FiscalYearService::MAX_DURATION_MONTHS,
        ]);
    }

    public function store(StoreFiscalYearRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('closeYear', Account::class);

        $org = Organization::findOrFail($currentOrg->id());

        try {
            $this->fiscalYears->create($org, FiscalYearData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', __('app.fiscal_year_created'));
    }

    public function update(UpdateFiscalYearRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorize('closeYear', Account::class);
        $this->ensureBelongsToCurrentOrg($fiscalYear);

        try {
            $this->fiscalYears->update($fiscalYear, FiscalYearData::fromArray($request->validated()));
        } catch (DomainException $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', __('app.fiscal_year_updated'));
    }

    public function destroy(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorize('closeYear', Account::class);
        $this->ensureBelongsToCurrentOrg($fiscalYear);

        if ($fiscalYear->status !== FiscalYearStatus::Planned) {
            return $this->backWithError(__('app.fiscal_year_only_planned_deletable'));
        }

        $fiscalYear->delete();

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', __('app.fiscal_year_deleted'));
    }

    /**
     * Defence in depth: even though BelongsToOrganization scopes by current
     * org, route-model bound resources should be re-checked.
     */
    private function ensureBelongsToCurrentOrg(FiscalYear $fiscalYear): void
    {
        $orgId = app(CurrentOrganization::class)->id();
        if ($fiscalYear->organization_id !== $orgId) {
            abort(404);
        }
    }
}
