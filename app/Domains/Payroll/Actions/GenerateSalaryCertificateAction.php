<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Support\Money;
use Illuminate\Support\Collection;

final class GenerateSalaryCertificateAction
{
    /**
     * @return array{
     *     employee: Employee,
     *     organization: Organization,
     *     year: int,
     *     months_covered: int,
     *     gross_salary: string,
     *     avs_employee: string,
     *     ac_employee: string,
     *     aanp_employee: string,
     *     lpp_employee: string,
     *     source_tax: string,
     *     reimbursements: string,
     *     net_salary: string,
     *     total_paid: string,
     *     employer_charges: string,
     * }
     */
    public function execute(Employee $employee, int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Salary certificate year is outside the supported range.');
        }

        $employee->loadMissing('organization');

        $slips = SalarySlip::query()
            ->where('employee_id', $employee->id)
            ->where('organization_id', $employee->organization_id)
            ->where('period_year', $year)
            ->whereNotNull('posted_at')
            ->orderBy('period_month')
            ->get();

        $grossSalary = $this->sumSlipValues($slips, 'gross_salary');
        $sourceTax = $this->sumDeduction($slips, 'source_tax');
        $reimbursements = $this->sumAdjustment($slips, 'reimbursement_amount');
        $employeeDeductions = $this->sumDeduction($slips, 'total_employee');
        $employeeDeductions = Money::add($employeeDeductions, $sourceTax);
        $netSalary = Money::subtract($grossSalary, $employeeDeductions);
        $totalPaid = Money::add($netSalary, $reimbursements);

        return [
            'employee' => $employee,
            'organization' => $employee->organization,
            'year' => $year,
            'months_covered' => $slips->pluck('period_month')->unique()->count(),
            'gross_salary' => $grossSalary,
            'avs_employee' => $this->sumDeduction($slips, 'avs_employee'),
            'ac_employee' => $this->sumDeduction($slips, 'ac_employee'),
            'aanp_employee' => $this->sumDeduction($slips, 'aanp_employee'),
            'lpp_employee' => $this->sumDeduction($slips, 'lpp_employee'),
            'source_tax' => $sourceTax,
            'reimbursements' => $reimbursements,
            'net_salary' => $netSalary,
            'total_paid' => $totalPaid,
            'employer_charges' => $this->sumDeduction($slips, 'total_employer'),
        ];
    }

    /**
     * @param  Collection<int, SalarySlip>  $slips
     */
    private function sumSlipValues(Collection $slips, string $attribute): string
    {
        return $slips->reduce(
            fn (string $total, SalarySlip $slip): string => Money::add($total, (string) $slip->{$attribute}),
            Money::zero(),
        );
    }

    /**
     * @param  Collection<int, SalarySlip>  $slips
     */
    private function sumDeduction(Collection $slips, string $key): string
    {
        return $slips->reduce(
            fn (string $total, SalarySlip $slip): string => Money::add(
                $total,
                (string) ($slip->deductions[$key]
                    ?? ($key === 'source_tax' ? $slip->getAttribute('source_tax_amount') : null)
                    ?? '0.00'),
            ),
            Money::zero(),
        );
    }

    /**
     * @param  Collection<int, SalarySlip>  $slips
     */
    private function sumAdjustment(Collection $slips, string $key): string
    {
        return $slips->reduce(
            fn (string $total, SalarySlip $slip): string => Money::add(
                $total,
                (string) ($slip->adjustments[$key] ?? $slip->deductions[$key] ?? '0.00'),
            ),
            Money::zero(),
        );
    }
}
