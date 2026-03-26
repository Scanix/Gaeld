<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Requests\BalanceSheetRequest;
use App\Domains\Reporting\Requests\ProfitAndLossRequest;
use App\Domains\Reporting\Services\ReportingService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function profitAndLoss(ProfitAndLossRequest $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();

        $orgId = $currentOrg->id();
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $reportingService->profitAndLoss($orgId, $from, $to);

        return Inertia::render('Reports/ProfitAndLoss', [
            'report' => $report,
        ]);
    }

    public function balanceSheet(BalanceSheetRequest $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();

        $orgId = $currentOrg->id();
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();

        $report = $reportingService->balanceSheet($orgId, $asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
        ]);
    }
}
