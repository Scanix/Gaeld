<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Models\Employee;

class UpdateEmployeeAction
{
    public function execute(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }
}
