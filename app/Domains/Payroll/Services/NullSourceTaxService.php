<?php

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Contracts\SourceTaxServiceInterface;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;

/**
 * No-op source-tax driver used by Community Edition.
 */
final class NullSourceTaxService implements SourceTaxServiceInterface
{
    public function applyToSlip(SalarySlip $slip, Employee $employee): void {}
}
