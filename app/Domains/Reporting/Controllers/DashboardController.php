<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Enums\OrganizationModule;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
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
        $org = $currentOrg->get();
        $metrics = $dashboardService->metrics($orgId);

        $isEmptyState = $metrics['revenue'] === '0.00'
            && $metrics['expenses'] === '0.00'
            && count($metrics['recentTransactions']) === 0;

        $expiredFiscalYear = FiscalYear::query()
            ->where('status', FiscalYearStatus::Expired->value)
            ->orderBy('end_date', 'desc')
            ->first();

        return Inertia::render('Dashboard', array_merge($metrics, [
            'isEmptyState' => $isEmptyState,
            'hasExportModule' => FeatureFlag::enabledForOrg(OrganizationModule::FiduciaryExport->value, $org),
            'expiredFiscalYear' => $expiredFiscalYear ? [
                'id' => $expiredFiscalYear->id,
                'name' => $expiredFiscalYear->name,
                'end_date' => $expiredFiscalYear->end_date->toDateString(),
            ] : null,
        ]));
    }
}
