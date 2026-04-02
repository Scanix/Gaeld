<?php

namespace Database\Factories\Domains\Expenses\Models;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category' => fake()->randomElement(['software', 'office', 'travel', 'consulting']),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'vat_amount' => '0.00',
            'date' => fake()->date(),
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ];
    }
}
