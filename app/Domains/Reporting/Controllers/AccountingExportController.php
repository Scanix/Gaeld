<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Jobs\GenerateAccountingExportJob;
use App\Domains\Reporting\Requests\GenerateAccountingExportRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generates and downloads end-of-year accounting export packages.
 */
class AccountingExportController extends Controller
{
    public function index(CurrentOrganization $currentOrg, FiscalYearService $fiscalYears): Response
    {
        $this->authorize('viewAny', Account::class);

        $currentYear = now()->year;
        $organization = $currentOrg->get();
        $periods = FiscalYear::query()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (FiscalYear $fiscalYear): array => [
                'id' => $fiscalYear->id,
                'label' => $fiscalYear->name,
                'start_date' => $fiscalYear->start_date->toDateString(),
                'end_date' => $fiscalYear->end_date->toDateString(),
                'is_legacy_fallback' => false,
            ])
            ->values()
            ->all();

        if ($periods === []) {
            $periods = array_map(function (int $year) use ($fiscalYears, $organization): array {
                $period = $fiscalYears->resolvePeriod($organization, null, $year);

                return [
                    'id' => null,
                    'label' => $period->label,
                    'start_date' => $period->fromDate,
                    'end_date' => $period->toDate,
                    'is_legacy_fallback' => true,
                ];
            }, range($currentYear, $currentYear - 5));
        }

        $currentPeriod = $periods[0] ?? null;

        return Inertia::render('Accounting/Export', [
            'fiscalYears' => $periods,
            'currentFiscalYear' => $currentPeriod['label'] ?? (string) $currentYear,
            'currentFiscalYearId' => $currentPeriod['id'] ?? null,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    public function generate(
        GenerateAccountingExportRequest $request,
        CurrentOrganization $currentOrg,
        FiscalYearService $fiscalYears,
    ): RedirectResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $period = $fiscalYears->resolvePeriod(
            $currentOrg->get(),
            $validated['fiscal_year_id'] ?? null,
            isset($validated['fiscal_year']) ? (int) $validated['fiscal_year'] : null,
        );

        GenerateAccountingExportJob::dispatch(
            $currentOrg->id(),
            $period->label,
            (string) $request->user()->id,
            $validated['fiscal_year_id'] ?? null,
        );

        return redirect()->route('accounting.export')
            ->with('success', __('app.export_dispatched'));
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = $request->query('path', '');

        $absolutePath = Storage::disk('local')->path('exports/'.basename($path));

        abort_unless(file_exists($absolutePath), 404);

        return response()->download($absolutePath);
    }
}
