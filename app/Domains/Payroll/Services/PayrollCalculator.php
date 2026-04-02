<?php

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\DeductionRate;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Calculates employee salary slips including gross-to-net computation,
 * Swiss social deductions, and pro-rata handling for partial months.
 */
class PayrollCalculator
{
    public function __construct(
        private SwissDeductionService $deductionService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Calculation
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate salary for a given employee and period.
     * Handles pro-rata for partial months (entry/exit mid-month).
     */
    public function calculate(Employee $employee, int $month, int $year): SalarySlip
    {
        $grossSalary = $this->proRataGross($employee, $month, $year);

        $rates = DeductionRate::where('organization_id', $employee->organization_id)
            ->where('is_active', true)
            ->get();

        $deductions = $this->deductionService->calculateDeductions($grossSalary, $rates);

        return new SalarySlip([
            'employee_id' => $employee->id,
            'organization_id' => $employee->organization_id,
            'period_month' => $month,
            'period_year' => $year,
            'gross_salary' => $grossSalary,
            'net_salary' => $deductions['net_salary'],
            'deductions' => $deductions,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate pro-rata gross salary for partial months.
     */
    private function proRataGross(Employee $employee, int $month, int $year): string
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $totalDays = $periodStart->daysInMonth;

        $effectiveStart = $periodStart->copy();
        $effectiveEnd = $periodEnd->copy();

        // Employee started mid-month
        if ($employee->entry_date->greaterThan($periodStart) && $employee->entry_date->lessThanOrEqualTo($periodEnd)) {
            $effectiveStart = $employee->entry_date->copy();
        }

        // Employee exited mid-month
        if ($employee->exit_date && $employee->exit_date->greaterThanOrEqualTo($periodStart) && $employee->exit_date->lessThan($periodEnd)) {
            $effectiveEnd = $employee->exit_date->copy();
        }

        $workedDays = $effectiveStart->diffInDays($effectiveEnd) + 1;

        if ($workedDays >= $totalDays) {
            return $employee->gross_salary;
        }

        // Pro-rata: gross * workedDays / totalDays
        return Money::divide(
            Money::multiply($employee->gross_salary, (string) $workedDays),
            (string) $totalDays,
        );
    }
}
