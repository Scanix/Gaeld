<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Payroll\Actions\CreateEmployeeAction;
use App\Domains\Payroll\Actions\UpdateEmployeeAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Requests\StoreEmployeeRequest;
use App\Domains\Payroll\Requests\UpdateEmployeeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee record CRUD within the payroll module.
 */
class EmployeeController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Employee::class);

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
        $this->authorize('create', Employee::class);

        return Inertia::render('Payroll/Employees/Create');
    }

    public function store(StoreEmployeeRequest $request, CurrentOrganization $currentOrg, CreateEmployeeAction $action): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $action->execute($currentOrg->id(), $request->validated());

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', __('app.employee_created'));
    }

    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        return Inertia::render('Payroll/Employees/Show', [
            'employee' => $employee->load('salarySlips'),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('Payroll/Employees/Edit', [
            'employee' => $employee,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployeeAction $action): RedirectResponse
    {
        $this->authorize('update', $employee);

        $action->execute($employee, $request->validated());

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', __('app.employee_updated'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('payroll.employees.index')
            ->with('success', __('app.employee_deleted'));
    }
}
