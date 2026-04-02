<?php

namespace Database\Factories\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(2, true),
            'type' => AccountType::Asset->value,
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(['type' => AccountType::Asset->value]);
    }

    public function liability(): static
    {
        return $this->state(['type' => AccountType::Liability->value]);
    }

    public function equity(): static
    {
        return $this->state(['type' => AccountType::Equity->value]);
    }

    public function revenue(): static
    {
        return $this->state(['type' => AccountType::Revenue->value]);
    }

    public function expense(): static
    {
        return $this->state(['type' => AccountType::Expense->value]);
    }
}
