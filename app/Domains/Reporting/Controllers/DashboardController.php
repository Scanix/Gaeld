<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): Response
    {
        $this->authorize('viewAny', \App\Domains\Accounting\Models\Account::class);

        $organization = app('current_organization');

        return Inertia::render('Dashboard', $dashboardService->metrics($organization->id));
    }
}
