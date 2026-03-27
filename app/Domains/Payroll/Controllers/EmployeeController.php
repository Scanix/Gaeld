<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $employees = Employee::query()
            ->where('organization_id', $currentOrg->id())
            ->orderBy('last_name')
            ->paginate(25);

        return Inertia::render('Payroll/Employees/Index', [
            'employees' => $employees,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Payroll/Employees/Create');
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'ahv_number' => ['nullable', 'string', 'max:16'],
            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'gross_salary' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_source_tax_subject' => ['boolean'],
        ]);

        $validated['organization_id'] = $currentOrg->id();

        $employee = Employee::create($validated);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', __('app.employee_created'));
    }

    public function show(Employee $employee): Response
    {
        return Inertia::render('Payroll/Employees/Show', [
            'employee' => $employee->load('salarySlips'),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Payroll/Employees/Edit', [
            'employee' => $employee,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'ahv_number' => ['nullable', 'string', 'max:16'],
            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'gross_salary' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_source_tax_subject' => ['boolean'],
        ]);

        $employee->update($validated);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', __('app.employee_updated'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('payroll.employees.index')
            ->with('success', __('app.employee_deleted'));
    }
}
