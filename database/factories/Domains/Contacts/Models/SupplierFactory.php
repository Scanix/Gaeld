<?php

namespace Database\Factories\Domains\Contacts\Models;

use App\Domains\Contacts\Models\Supplier;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

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
