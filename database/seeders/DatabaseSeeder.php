<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SwissChartOfAccountsSeeder::class,
            SwissVatRatesSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
