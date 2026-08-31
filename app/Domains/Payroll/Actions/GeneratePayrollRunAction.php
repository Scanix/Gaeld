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
     * @param  array<int, string>  $employeeIds  Optional subset of employee UUIDs to process. Empty array = all active employees.
     * @return Collection<int, SalarySlip>
     */
    public function execute(string $orgId, int $month, int $year, bool $shouldPost = false, array $employeeIds = []): Collection
    {
        $employees = $this->employees($orgId, $employeeIds);

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

    /**
     * Calculate a payroll preview without persisting salary slips.
     *
     * @param  array<int, string>  $employeeIds
     * @return Collection<int, SalarySlip>
     */
    public function preview(string $orgId, int $month, int $year, array $employeeIds = []): Collection
    {
        return $this->employees($orgId, $employeeIds)
            ->map(fn (Employee $employee): SalarySlip => $this->calculator->calculate($employee, $month, $year))
            ->values();
    }

    /**
     * @param  array<int, string>  $employeeIds
     * @return Collection<int, Employee>
     */
    private function employees(string $orgId, array $employeeIds): Collection
    {
        return Employee::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->when($employeeIds !== [], fn ($query) => $query->whereIn('id', $employeeIds))
            ->get();
    }
}
