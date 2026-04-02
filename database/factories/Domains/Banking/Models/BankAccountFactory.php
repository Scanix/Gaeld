<?php

namespace Database\Factories\Domains\Banking\Models;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'currency' => 'CHF',
            'balance' => '0.00',
            'is_active' => true,
        ];
    }
}
