<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Payroll\Actions\GeneratePayrollRunAction;
use App\Domains\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Triggers payroll calculation runs for a given period.
 *
 * Authorizes against Employee::class because no PayrollRun model exists.
 * EmployeePolicy maps viewAny/create to payroll.view/payroll.create permissions.
 */
class PayrollRunController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Employee::class);
        abort_if(! $request->user()->hasRole('owner'), 403);

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

        $currentYear = now()->year;
        $fiscalYears = range($currentYear, $currentYear - 4);

        return Inertia::render('Payroll/Run', [
            'employees' => $employees,
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function generate(Request $request, CurrentOrganization $currentOrg, GeneratePayrollRunAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Employee::class);
        abort_if(! $request->user()->hasRole('owner'), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
            'post' => ['boolean'],
        ]);

        $slips = $action->execute(
            $currentOrg->id(),
            (int) $validated['month'],
            (int) $validated['year'],
            $validated['post'] ?? false,
        );

        if ($request->wantsJson()) {
            return response()->json(['count' => $slips->count(), 'slip_ids' => $slips->pluck('id')]);
        }

        return redirect()->route('payroll.salarySlips.index')
            ->with('success', __('app.payroll_run_completed', ['count' => $slips->count()]));
    }
}
