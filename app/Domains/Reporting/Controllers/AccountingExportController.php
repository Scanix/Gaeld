<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Jobs\GenerateAccountingExportJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingExportController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $currentYear = now()->year;

        $fiscalYears = array_map(
            fn (int $y) => (string) $y,
            range($currentYear, $currentYear - 5),
        );

        return Inertia::render('Accounting/Export', [
            'fiscalYears' => $fiscalYears,
            'currentFiscalYear' => (string) $currentYear,
        ]);
    }

    public function generate(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validate([
            'fiscal_year' => ['required', 'digits:4', 'integer', 'min:2000', 'max:2100'],
        ]);

        GenerateAccountingExportJob::dispatch(
            $currentOrg->id(),
            $validated['fiscal_year'],
            (string) $request->user()->id,
        );

        return redirect()->route('accounting.export')
            ->with('flash', __('accounting.export_dispatched'));
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
