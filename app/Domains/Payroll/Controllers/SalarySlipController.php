<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Actions\UnpostPayrollAction;
use App\Domains\Payroll\Controllers\Concerns\EnsuresPayrollWritable;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
use App\Support\PdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salary slip viewing, posting, and PDF download.
 */
class SalarySlipController extends Controller
{
    use EnsuresPayrollWritable;

    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', SalarySlip::class);

        $year = $request->input('year');
        $month = $request->input('month');

        $query = SalarySlip::query()
            ->where('organization_id', $currentOrg->id())
            ->with('employee')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');

        $ownOnly = $request->user()->hasPermissionTo(Permission::PayrollSalarySlipsViewOwn)
            && ! $request->user()->hasPermissionTo(Permission::PayrollView);

        if ($ownOnly) {
            $query->whereNotNull('posted_at')
                ->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('user_id', $request->user()->id));
        }

        if ($year) {
            $query->where('period_year', (int) $year);
        }

        if ($month) {
            $query->where('period_month', (int) $month);
        }

        // Default year to the most recent slip's year if no filter provided
        $defaultYear = $year;
        if (! $defaultYear) {
            $latestSlip = SalarySlip::where('organization_id', $currentOrg->id())
                ->when($ownOnly, fn ($latestQuery) => $latestQuery
                    ->whereNotNull('posted_at')
                    ->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('user_id', $request->user()->id)))
                ->orderByDesc('period_year')
                ->first();
            $defaultYear = $latestSlip ? (string) $latestSlip->period_year : (string) now()->year;
        }

        return Inertia::render('Payroll/SalarySlips/Index', [
            'slips' => $query->paginate(25),
            'query' => [
                'year' => $defaultYear,
                'month' => $month ?? '',
            ],
            'ownOnly' => $ownOnly,
            'payrollWritable' => FeatureFlag::enabledForOrg('payroll', $currentOrg->get()),
        ]);
    }

    public function show(SalarySlip $slip): Response
    {
        $this->authorize('view', $slip);

        $ownOnly = request()->user()->hasPermissionTo(Permission::PayrollSalarySlipsViewOwn)
            && ! request()->user()->hasPermissionTo(Permission::PayrollView);

        return Inertia::render('Payroll/SalarySlips/Show', [
            'slip' => $ownOnly
                ? $slip->load('employee')->makeHidden(['journal_entry_id'])
                : $slip->load(['employee', 'journalEntry.lines.account']),
            'canManage' => ! $ownOnly,
        ]);
    }

    public function generate(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $this->ensurePayrollWritable(app(CurrentOrganization::class)->get());
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $slip = $calculator->calculate($employee, (int) $validated['month'], (int) $validated['year']);
        $slip->save();

        return redirect()->route('payroll.salarySlips.show', $slip)
            ->with('success', __('app.salary_slip_generated'));
    }

    public function post(Request $request, SalarySlip $slip, PostPayrollAction $action): RedirectResponse|JsonResponse
    {
        $this->ensurePayrollWritable($slip->organization);
        $this->authorize('update', $slip);

        if ($slip->isPosted()) {
            if ($request->wantsJson()) {
                return new JsonResponse(['message' => __('app.salary_slip_already_posted')], 422);
            }

            return redirect()->back()->with('error', __('app.salary_slip_already_posted'));
        }

        $action->execute($slip);

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => __('app.salary_slip_posted'), 'id' => $slip->id]);
        }

        return redirect()->route('payroll.salarySlips.show', $slip)
            ->with('success', __('app.salary_slip_posted'));
    }

    public function unpost(Request $request, SalarySlip $slip, UnpostPayrollAction $action): RedirectResponse|JsonResponse
    {
        $this->ensurePayrollWritable($slip->organization);
        $this->authorize('update', $slip);

        if (! $slip->isPosted()) {
            if ($request->wantsJson()) {
                return new JsonResponse(['message' => __('app.salary_slip_not_posted')], 422);
            }

            return redirect()->back()->with('error', __('app.salary_slip_not_posted'));
        }

        $action->execute($slip);

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => __('app.salary_slip_unposted'), 'id' => $slip->id]);
        }

        return redirect()->route('payroll.salarySlips.show', $slip)
            ->with('success', __('app.salary_slip_unposted'));
    }

    public function destroy(SalarySlip $slip): RedirectResponse
    {
        $this->ensurePayrollWritable($slip->organization);
        $this->authorize('delete', $slip);

        if ($slip->isPosted()) {
            return redirect()->back()->with('error', __('app.salary_slip_must_be_unposted_first'));
        }

        $slip->delete();

        return redirect()->route('payroll.salarySlips.index')
            ->with('success', __('app.salary_slip_deleted'));
    }

    public function downloadPdf(SalarySlip $slip, PdfExportService $pdf): HttpResponse
    {
        $this->authorize('view', $slip);

        $slip->load('employee');

        return $pdf->download(
            'exports.salary-slip',
            ['slip' => $slip],
            "salary-slip-{$slip->employee->last_name}-{$slip->period_year}-{$slip->period_month}.pdf",
        );
    }
}
