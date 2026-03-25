<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\ReportingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function profitAndLoss(Request $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $currentOrg->id();
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $reportingService->profitAndLoss($orgId, $from, $to);

        return Inertia::render('Reports/ProfitAndLoss', [
            'report' => $report,
        ]);
    }

    public function balanceSheet(Request $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
        ]);

        $orgId = $currentOrg->id();
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();

        $report = $reportingService->balanceSheet($orgId, $asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
        ]);
    }
}
