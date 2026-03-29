<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;

/**
 * Generates salary slips for all active employees in a given payroll period.
 */
class GeneratePayrollRunAction
{
    public function __construct(
        private PayrollCalculator $calculator,
        private PostPayrollAction $postAction,
    ) {}

    public function execute(string $orgId, int $month, int $year, bool $shouldPost = false): int
    {
        $employees = Employee::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            $exists = SalarySlip::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $slip = $this->calculator->calculate($employee, $month, $year);
            $slip->save();

            if ($shouldPost) {
                $this->postAction->execute($slip);
            }

            $count++;
        }

        return $count;
    }
}
