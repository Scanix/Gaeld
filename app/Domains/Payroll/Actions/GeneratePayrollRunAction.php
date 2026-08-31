<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates salary slips for all active employees in a given payroll period.
 */
class GeneratePayrollRunAction
{
    public function __construct(
        private PayrollCalculator $calculator,
        private PostPayrollAction $postAction,
    ) {}

    /**
     * @param  array<int, string>  $employeeIds  Optional subset of employee UUIDs to process. Empty array = all active employees.
     * @param  array<int, array{employee_id: string, unpaid_leave_days?: int|string, reimbursement_amount?: string|int|float}>  $adjustments
     * @return Collection<int, SalarySlip>
     */
    public function execute(string $orgId, int $month, int $year, bool $shouldPost = false, array $employeeIds = [], array $adjustments = []): Collection
    {
        $employees = $this->employees($orgId, $month, $year, $employeeIds);
        $adjustmentsByEmployee = collect($adjustments)->keyBy('employee_id');

        $slips = collect();
        foreach ($employees as $employee) {
            $exists = SalarySlip::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $adjustment = $adjustmentsByEmployee->get($employee->id, []);
            $slip = DB::transaction(function () use ($employee, $month, $year, $shouldPost, $adjustment): SalarySlip {
                $slip = $this->calculator->calculate(
                    $employee,
                    $month,
                    $year,
                    (int) ($adjustment['unpaid_leave_days'] ?? 0),
                    (string) ($adjustment['reimbursement_amount'] ?? '0.00'),
                );
                $slip->save();

                if ($shouldPost) {
                    $this->postAction->execute($slip);
                }

                return $slip;
            });

            $slips->push($slip);
        }

        return $slips;
    }

    /**
     * Calculate a payroll preview without persisting salary slips.
     *
     * @param  array<int, string>  $employeeIds
     * @param  array<int, array{employee_id: string, unpaid_leave_days?: int|string, reimbursement_amount?: string|int|float}>  $adjustments
     * @return Collection<int, SalarySlip>
     */
    public function preview(string $orgId, int $month, int $year, array $employeeIds = [], array $adjustments = []): Collection
    {
        $adjustmentsByEmployee = collect($adjustments)->keyBy('employee_id');

        return $this->employees($orgId, $month, $year, $employeeIds)
            ->map(function (Employee $employee) use ($adjustmentsByEmployee, $month, $year): SalarySlip {
                $adjustment = $adjustmentsByEmployee->get($employee->id, []);

                return $this->calculator->calculate(
                    $employee,
                    $month,
                    $year,
                    (int) ($adjustment['unpaid_leave_days'] ?? 0),
                    (string) ($adjustment['reimbursement_amount'] ?? '0.00'),
                );
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $employeeIds
     * @return Collection<int, Employee>
     */
    private function employees(string $orgId, int $month, int $year, array $employeeIds): Collection
    {
        $periodStart = Carbon::create($year, $month, 1);
        $periodEnd = $periodStart->copy()->endOfMonth();

        return Employee::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereDate('entry_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('exit_date')
                    ->orWhereDate('exit_date', '>=', $periodStart);
            })
            ->when($employeeIds !== [], fn ($query) => $query->whereIn('id', $employeeIds))
            ->get();
    }
}
