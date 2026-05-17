<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Main dashboard: KPI widgets, revenue/expense charts, and recent activity.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $orgId = $currentOrg->id();

        return Inertia::render('Dashboard', $dashboardService->metrics($orgId));
    }
}
