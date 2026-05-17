<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class FiscalYearsTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private User $accountant;

    private User $viewer;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->accountant = User::factory()->create();
        $this->viewer = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Fiscal Year Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->organization->users()->attach($this->accountant->id, ['role' => 'accountant']);
        $this->assignOrganizationRole($this->accountant, $this->organization, 'accountant');

        $this->organization->users()->attach($this->viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($this->viewer, $this->organization, 'viewer');

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Debtors',
            'type' => AccountType::Asset->value,
        ]);

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function asViewer(): self
    {
        return $this->actingAs($this->viewer)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    public function test_owner_can_view_fiscal_years_index(): void
    {
        $this->asOwner()
            ->get('/accounting/fiscal-years')
            ->assertOk();
    }

    public function test_viewer_cannot_view_fiscal_years_index(): void
    {
        $this->asViewer()
            ->get('/accounting/fiscal-years')
            ->assertForbidden();
    }

    public function test_owner_can_create_standard_fiscal_year(): void
    {
        $this->asOwner()
            ->post('/accounting/fiscal-years', [
                'name' => 'FY 2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fiscal_years', [
            'organization_id' => $this->organization->id,
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);
    }

    public function test_owner_can_create_long_fiscal_year_swiss_founding(): void
    {
        // The trigger scenario from issue #17: founded 3 Oct 2025, long FY → 31 Dec 2026
        $this->asOwner()
            ->post('/accounting/fiscal-years', [
                'name' => 'Long FY 2025-2026',
                'start_date' => '2025-10-03',
                'end_date' => '2026-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fiscal_years', [
            'organization_id' => $this->organization->id,
            'start_date' => '2025-10-03',
            'end_date' => '2026-12-31',
        ]);
    }

    public function test_create_rejects_overlap(): void
    {
        FiscalYear::factory()->for($this->organization)->create([
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);

        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->post('/accounting/fiscal-years', [
                'name' => 'FY overlap',
                'start_date' => '2027-06-01',
                'end_date' => '2028-05-31',
            ])
            ->assertRedirect('/accounting/fiscal-years')
            ->assertSessionHas('error');
    }

    public function test_create_rejects_too_long_period(): void
    {
        // > 23 months
        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->post('/accounting/fiscal-years', [
                'name' => 'Too long',
                'start_date' => '2027-01-01',
                'end_date' => '2029-06-30',
            ])
            ->assertRedirect('/accounting/fiscal-years')
            ->assertSessionHas('error');
    }

    public function test_create_validates_end_after_start(): void
    {
        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->post('/accounting/fiscal-years', [
                'name' => 'Bad dates',
                'start_date' => '2027-12-31',
                'end_date' => '2027-01-01',
            ])
            ->assertSessionHasErrors(['end_date']);
    }

    public function test_only_planned_fiscal_year_can_be_deleted(): void
    {
        $planned = FiscalYear::factory()->for($this->organization)->planned()->create();
        $operative = FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
        ]);

        $this->asOwner()
            ->delete("/accounting/fiscal-years/{$planned->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('fiscal_years', ['id' => $planned->id]);

        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->delete("/accounting/fiscal-years/{$operative->id}")
            ->assertRedirect('/accounting/fiscal-years')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('fiscal_years', ['id' => $operative->id]);
    }

    public function test_update_locked_dates_for_operative_year(): void
    {
        $fy = FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
        ]);

        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->put("/accounting/fiscal-years/{$fy->id}", [
                'name' => 'Renamed',
                'start_date' => '2030-02-01',
                'end_date' => '2030-12-31',
            ])
            ->assertRedirect('/accounting/fiscal-years')
            ->assertSessionHas('error');
    }

    public function test_update_can_rename_operative_year(): void
    {
        $fy = FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
        ]);

        $this->asOwner()
            ->put("/accounting/fiscal-years/{$fy->id}", [
                'name' => 'Renamed FY',
                'start_date' => $fy->start_date->toDateString(),
                'end_date' => $fy->end_date->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fiscal_years', ['id' => $fy->id, 'name' => 'Renamed FY']);
    }

    public function test_closed_fiscal_year_cannot_be_updated(): void
    {
        $fy = FiscalYear::factory()->for($this->organization)->closed()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->asOwner()
            ->from('/accounting/fiscal-years')
            ->put("/accounting/fiscal-years/{$fy->id}", [
                'name' => 'Should fail',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ])
            ->assertRedirect('/accounting/fiscal-years')
            ->assertSessionHas('error');
    }

    public function test_user_cannot_modify_other_org_fiscal_year(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'currency' => 'CHF']);
        $fy = FiscalYear::factory()->for($otherOrg)->planned()->create();

        $this->asOwner()
            ->delete("/accounting/fiscal-years/{$fy->id}")
            ->assertNotFound();
    }
}
