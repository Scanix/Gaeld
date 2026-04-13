<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Organizations\Models\Organization;
use Database\Seeders\SwissVatRatesSeeder;

/**
 * Seeds initial accounting data (chart of accounts, VAT rates) when
 * a new organization is created during setup or provisioning.
 */
class OrganizationSetupService
{
    public function __construct(
        private readonly ChartTemplateService $chartTemplateService,
        private readonly SwissVatRatesSeeder $vatRatesSeeder,
    ) {}

    /**
     * Seed a chart of accounts (and optionally VAT rates) for the organization.
     */
    public function seedChartOfAccounts(Organization $organization, string $templateKey): void
    {
        $this->chartTemplateService->seedTemplate($organization, $templateKey);
        $this->chartTemplateService->ensureSystemAccounts($organization);

        if ($this->chartTemplateService->templateSeedsVatRates($templateKey)) {
            $this->vatRatesSeeder->run($organization);
        }
    }

    /**
     * Ensure system accounts exist even when no chart template is selected.
     */
    public function ensureSystemAccounts(Organization $organization): void
    {
        $this->chartTemplateService->ensureSystemAccounts($organization);
    }

    /**
     * @deprecated Use seedChartOfAccounts() instead.
     */
    public function seedSwissDefaults(Organization $organization): void
    {
        $this->seedChartOfAccounts($organization, 'swiss_sme');
    }
}
