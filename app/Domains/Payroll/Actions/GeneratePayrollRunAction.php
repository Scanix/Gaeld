<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
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
     * @return Collection<int, SalarySlip>
     */
    public function execute(string $orgId, int $month, int $year, bool $shouldPost = false): Collection
    {
        $employees = Employee::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        $slips = collect();
        foreach ($employees as $employee) {
            $exists = SalarySlip::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $slip = DB::transaction(function () use ($employee, $month, $year, $shouldPost): SalarySlip {
                $slip = $this->calculator->calculate($employee, $month, $year);
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
}
