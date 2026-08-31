<?php

namespace Tests\Feature\Organizations;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Banking\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization(['email_verified_at' => now()]);
        $this->user->update(['onboarding_completed_at' => null]);
    }

    public function test_wizard_renders_for_user_who_has_not_completed_onboarding(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);

        $response = $this->actAsOrg()->get(route('onboarding.wizard'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Onboarding/Wizard')
                ->has('modules')
                ->has('modulePresets')
        );
    }

    public function test_wizard_redirects_to_dashboard_when_onboarding_already_completed(): void
    {
        $this->user->update(['onboarding_completed_at' => now()]);

        $response = $this->actAsOrg()->get(route('onboarding.wizard'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_redirects_to_wizard_when_onboarding_is_incomplete(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);

        $response = $this->actAsOrg()->get(route('dashboard'));

        $response->assertRedirect(route('onboarding.wizard'));
    }

    public function test_store_persists_company_details_and_enabled_modules_and_sets_flag(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);

        $response = $this->actAsOrg()->post(route('onboarding.wizard.store'), [
            'business_type' => 'sme',
            'modules' => [
                'budgets' => true,
                'payroll' => true,
                'consolidation' => false,
            ],
            'legal_name' => 'Acme Holding SA',
            'address' => 'Rue du Test 1',
            'city' => 'Geneva',
            'postal_code' => '1200',
            'canton' => 'GE',
            'vat_number' => 'CHE-123.456.789',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->organization->refresh();
        $this->assertSame('Acme Holding SA', $this->organization->legal_name);
        $this->assertSame('Geneva', $this->organization->city);
        $this->assertSame('sme', $this->organization->business_type?->value);
        $this->assertTrue($this->organization->enabled_modules['budgets']);
        $this->assertTrue($this->organization->enabled_modules['payroll']);
        $this->assertFalse($this->organization->enabled_modules['consolidation']);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }

    public function test_store_creates_fiscal_year_when_dates_provided(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);

        $this->actAsOrg()->post(route('onboarding.wizard.store'), [
            'fiscal_year_name' => 'FY 2025',
            'fiscal_year_start' => '2025-01-01',
            'fiscal_year_end' => '2025-12-31',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas((new FiscalYear)->getTable(), [
            'organization_id' => $this->organization->id,
            'name' => 'FY 2025',
        ]);
    }

    public function test_store_creates_bank_account_when_name_provided(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);
        $bankAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->actAsOrg()->post(route('onboarding.wizard.store'), [
            'bank_account_name' => 'Main CHF Account',
            'bank_name' => 'Test Bank',
            'iban' => 'CH9300762011623852957',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas((new BankAccount)->getTable(), [
            'organization_id' => $this->organization->id,
            'name' => 'Main CHF Account',
            'account_id' => $bankAccount->id,
        ]);
    }

    public function test_skip_sets_completion_flag_and_redirects(): void
    {
        $this->user->update(['onboarding_completed_at' => null]);

        $response = $this->actAsOrg()->post(route('onboarding.wizard.skip'));

        $response->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
    }
}
