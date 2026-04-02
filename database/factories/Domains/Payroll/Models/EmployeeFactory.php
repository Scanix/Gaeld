<?php

namespace Database\Factories\Domains\Payroll\Models;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Payroll\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'ahv_number' => '756.'.fake()->numerify('####.####.##'),
            'entry_date' => '2025-01-01',
            'gross_salary' => '6000.00',
            'is_active' => true,
        ];
    }
}
