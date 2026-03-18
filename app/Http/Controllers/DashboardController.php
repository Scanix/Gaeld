<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): Response
    {
        $organization = app('current_organization');

        return Inertia::render('Dashboard', $dashboardService->getMetrics($organization->id));
    }
}
