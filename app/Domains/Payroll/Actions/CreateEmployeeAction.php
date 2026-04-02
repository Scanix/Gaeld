<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\DTOs\CreateEmployeeData;
use App\Domains\Payroll\Models\Employee;

/**
 * Adds a new employee record to an organization.
 */
class CreateEmployeeAction
{
    public function execute(CreateEmployeeData $data): Employee
    {
        return Employee::create($data->toArray());
    }
}
