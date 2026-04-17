<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\ChecklistService;
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
    public function index(Request $request, DashboardService $dashboardService, ChecklistService $checklistService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $orgId = $currentOrg->id();
        $checklist = $checklistService->checklist($orgId);

        // Hide getting_started section once the user has dismissed onboarding
        if ($request->user()->onboarding_completed_at) {
            $checklist['getting_started'] = [];
        }

        return Inertia::render('Dashboard', array_merge(
            $dashboardService->metrics($orgId),
            [
                'checklist' => $checklist,
            ],
        ));
    }
}
