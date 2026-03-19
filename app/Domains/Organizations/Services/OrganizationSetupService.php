<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\Organization;
use Database\Seeders\SwissChartOfAccountsSeeder;
use Database\Seeders\SwissVatRatesSeeder;

class OrganizationSetupService
{
    public function __construct(
        private readonly SwissChartOfAccountsSeeder $chartOfAccountsSeeder,
        private readonly SwissVatRatesSeeder $vatRatesSeeder,
    ) {}

    public function seedSwissDefaults(Organization $organization): void
    {
        $this->chartOfAccountsSeeder->run($organization);
        $this->vatRatesSeeder->run($organization);
    }
}