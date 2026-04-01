<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class YearEndClosingTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $owner;

    private User $accountant;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->accountant = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Year-End Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->organization->users()->attach($this->accountant->id, ['role' => 'accountant']);
        $this->assignOrganizationRole($this->accountant, $this->organization, 'accountant');

        // Create minimum accounts for the organization
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Debtors',
            'type' => AccountType::Asset->value,
        ]);
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function asAccountant(): self
    {
        return $this->actingAs($this->accountant)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    public function test_reopen_fiscal_year_as_owner(): void
    {
        $this->organization->closeFiscalYear(2025);
        $this->assertTrue($this->organization->isFiscalYearClosed(2025));

        $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2025));
    }

    public function test_accountant_cannot_reopen_fiscal_year(): void
    {
        $this->organization->closeFiscalYear(2025);

        $this->asAccountant()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertForbidden();

        $this->organization->refresh();
        $this->assertTrue($this->organization->isFiscalYearClosed(2025));
    }

    public function test_reopen_non_closed_year_returns_error(): void
    {
        $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_reopen_succeeds_and_redirects_with_success(): void
    {
        $this->organization->closeFiscalYear(2025);

        $response = $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2025));
    }

    public function test_index_passes_closed_years_to_frontend(): void
    {
        $this->organization->closeFiscalYear(2024);
        $this->organization->closeFiscalYear(2025);

        $response = $this->asOwner()
            ->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/YearEndClosing')
            ->has('closedYears', 2)
            ->where('closedYears', [2024, 2025])
            ->where('canReopenYear', true)
        );
    }

    public function test_accountant_sees_can_reopen_year_as_false(): void
    {
        $this->organization->closeFiscalYear(2025);

        $response = $this->asAccountant()
            ->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('canReopenYear', false)
        );
    }
}
