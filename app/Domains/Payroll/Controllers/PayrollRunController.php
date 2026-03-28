<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->where('organization_id', $currentOrg->id())
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

    public function generate(Request $request, CurrentOrganization $currentOrg, PayrollCalculator $calculator, PostPayrollAction $postAction): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
            'post' => ['boolean'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $shouldPost = $validated['post'] ?? false;

        $employees = Employee::query()
            ->where('organization_id', $currentOrg->id())
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            // Skip if slip already exists for this period
            $exists = SalarySlip::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $slip = $calculator->calculate($employee, $month, $year);
            $slip->save();

            if ($shouldPost) {
                $postAction->execute($slip);
            }

            $count++;
        }

        return redirect()->route('payroll.salarySlips.index')
            ->with('success', __('app.payroll_run_completed', ['count' => $count]));
    }
}
