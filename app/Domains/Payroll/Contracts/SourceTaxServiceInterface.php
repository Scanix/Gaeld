<?php

namespace App\Domains\Payroll\Contracts;

use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;

/**
 * Optional enterprise source-tax integration for salary slips.
 */
interface SourceTaxServiceInterface
{
    public function applyToSlip(SalarySlip $slip, Employee $employee): void;
}
