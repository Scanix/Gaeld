<?php

namespace App\Domains\Organizations\Services;

use Database\Seeders\SwissChartOfAccountsSeeder;
use Database\Seeders\SwissVatRatesSeeder;

class OrganizationSetupService
{
    public function __construct(
        private readonly SwissChartOfAccountsSeeder $chartOfAccountsSeeder,
        private readonly SwissVatRatesSeeder $vatRatesSeeder,
    ) {}

    public function seedSwissDefaults(): void
    {
        $this->chartOfAccountsSeeder->run();
        $this->vatRatesSeeder->run();
    }
}