<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Expenses\Models\ExpenseCategory;
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
        $this->ensureDefaultExpenseCategories($organization);

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

    private function ensureDefaultExpenseCategories(Organization $organization): void
    {
        $resaleAccountId = Account::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('code', ExpenseCategory::RESALE_ACCOUNT_CODE)
            ->where('type', AccountType::Expense)
            ->where('is_active', true)
            ->value('id');

        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $sortOrder => $name) {
            $category = ExpenseCategory::withoutGlobalScopes()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $name,
                ],
                [
                    'is_default' => true,
                    'sort_order' => $sortOrder,
                ],
            );

            if ($name === ExpenseCategory::RESALE_CATEGORY && $resaleAccountId !== null && $category->default_expense_account_id === null) {
                $category->update(['default_expense_account_id' => $resaleAccountId]);
            }
        }
    }
}
