<?php

namespace Database\Factories\Domains\Contacts\Models;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'country' => 'CH',
            'currency' => 'CHF',
        ];
    }
}
