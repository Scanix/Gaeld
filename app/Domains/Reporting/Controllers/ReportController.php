<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Reporting\Services\ReportingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function profitAndLoss(Request $request, ReportingService $reportingService): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $report = $reportingService->profitAndLoss($orgId, $from, $to);

        return Inertia::render('Reports/ProfitAndLoss', [
            'report' => $report,
        ]);
    }

    public function balanceSheet(Request $request, ReportingService $reportingService): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        $report = $reportingService->balanceSheet($orgId, $asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
        ]);
    }
}
