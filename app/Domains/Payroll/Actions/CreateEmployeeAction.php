<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Models\Employee;

/**
 * Adds a new employee record to an organization.
 */
class CreateEmployeeAction
{
    public function execute(string $orgId, array $data): Employee
    {
        $data['organization_id'] = $orgId;

        return Employee::create($data);
    }
}
