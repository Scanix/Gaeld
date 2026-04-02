<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\DTOs\UpdateEmployeeData;
use App\Domains\Payroll\Models\Employee;

class UpdateEmployeeAction
{
    public function execute(Employee $employee, UpdateEmployeeData $data): Employee
    {
        $employee->update($data->toArray());

        return $employee->fresh();
    }
}
