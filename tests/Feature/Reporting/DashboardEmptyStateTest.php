<?php

namespace Tests\Feature\Reporting;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\OrganizationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class DashboardEmptyStateTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_new_organization_with_no_activity_gets_empty_state_true(): void
    {
        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('isEmptyState', true)
        );
    }

    public function test_dashboard_has_export_module_prop_matching_org_modules(): void
    {
        // Enable fiduciary_export for the org
        $this->org->update([
            'enabled_modules' => [OrganizationModule::FiduciaryExport->value => true],
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('hasExportModule', true)
        );
    }

    public function test_dashboard_has_export_module_false_when_disabled(): void
    {
        $this->org->update([
            'enabled_modules' => [OrganizationModule::FiduciaryExport->value => false],
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('hasExportModule', false)
        );
    }

    public function test_org_with_expense_gets_empty_state_false(): void
    {
        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Office',
            'description' => 'Desk',
            'amount' => '500.00',
            'vat_amount' => '0.00',
            'date' => now()->toDateString(),
            'status' => 'approved',
            'currency' => 'CHF',
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('isEmptyState', false)
        );
    }
}
