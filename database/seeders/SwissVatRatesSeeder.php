<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Swiss VAT rates as of 2024.
 */
class SwissVatRatesSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();

        if (! $organization) {
            return;
        }

        $rates = [
            [
                'name' => 'Standard Rate',
                'rate' => 8.10,
                'code' => 'NORMAL',
                'is_default' => true,
            ],
            [
                'name' => 'Reduced Rate',
                'rate' => 2.60,
                'code' => 'REDUCED',
                'is_default' => false,
            ],
            [
                'name' => 'Special Rate (Accommodation)',
                'rate' => 3.80,
                'code' => 'ACCOMMODATION',
                'is_default' => false,
            ],
            [
                'name' => 'Exempt',
                'rate' => 0.00,
                'code' => 'EXEMPT',
                'is_default' => false,
            ],
        ];

        foreach ($rates as $rate) {
            VatRate::create(array_merge($rate, [
                'organization_id' => $organization->id,
            ]));
        }
    }
}
