<?php

namespace Database\Factories\Domains\Accounting\Models;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VatRate>
 */
class VatRateFactory extends Factory
{
    protected $model = VatRate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
            'is_active' => true,
        ];
    }

    public function reduced(): static
    {
        return $this->state([
            'name' => 'Reduced',
            'rate' => 2.60,
            'code' => 'REDUCED',
            'is_default' => false,
        ]);
    }
}
