<?php

namespace Database\Factories\Domains\Organizations\Models;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'CHF',
            'country' => 'CH',
        ];
    }
}
