<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class OrganizationCrudFlowTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private User $member;

    private User $outsider;

    private Organization $primaryOrganization;

    private Organization $secondaryOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->primaryOrganization = Organization::create([
            'name' => 'Primary Org',
            'currency' => 'CHF',
        ]);
        $this->secondaryOrganization = Organization::create([
            'name' => 'Secondary Org',
            'currency' => 'EUR',
        ]);

        $this->primaryOrganization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->primaryOrganization, 'owner');
        $this->primaryOrganization->users()->attach($this->member->id, ['role' => 'member']);
        $this->assignOrganizationRole($this->member, $this->primaryOrganization, 'member');
        $this->secondaryOrganization->users()->attach($this->outsider->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->outsider, $this->secondaryOrganization, 'owner');

        $this->ensureSubscriptionIfSaas($this->primaryOrganization);
        $this->ensureSubscriptionIfSaas($this->secondaryOrganization);
    }

    public function test_owner_can_view_organization_index_and_only_see_memberships(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get('/organizations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.id', $this->primaryOrganization->id)
            ->where('organizations.0.name', 'Primary Org'));
    }

    public function test_member_can_view_organization_show_but_cannot_view_foreign_org(): void
    {
        $allowed = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get("/organizations/{$this->primaryOrganization->id}");

        $allowed->assertStatus(200);
        $allowed->assertInertia(fn ($page) => $page
            ->component('Organizations/Show')
            ->where('organization.id', $this->primaryOrganization->id));

        $forbidden = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get("/organizations/{$this->secondaryOrganization->id}");

        $forbidden->assertForbidden();
    }

    public function test_owner_can_create_another_organization_and_is_attached_as_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/organizations', [
                'name' => 'Created Org',
                'legal_name' => 'Created Org SA',
                'address' => 'Rue du Lac 1',
                'city' => 'Geneva',
                'postal_code' => '1200',
                'canton' => 'GE',
                'country' => 'CH',
                'vat_number' => 'CHE-111.222.333',
                'currency' => 'CHF',
                'locale' => 'en',
                'chart_of_accounts' => 'none',
            ]);

        $organization = Organization::where('name', 'Created Org')->firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization));
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
        $this->assertSame('Created Org SA', $organization->legal_name);
    }

    public function test_owner_can_create_another_organization_with_a_chart_template(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/organizations', [
                'name' => 'Created Swiss SME Org',
                'currency' => 'CHF',
                'locale' => 'en',
                'chart_of_accounts' => 'swiss_sme',
                'business_type' => 'sme',
            ]);

        $organization = Organization::where('name', 'Created Swiss SME Org')->firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization));
        $this->assertDatabaseHas('accounts', [
            'organization_id' => $organization->id,
            'code' => '1020',
        ]);
    }

    public function test_owner_can_delete_organization_and_it_is_removed_from_db(): void
    {
        $orgToDelete = Organization::create(['name' => 'To Delete', 'currency' => 'CHF']);
        $orgToDelete->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $orgToDelete, 'owner');

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->delete("/organizations/{$orgToDelete->id}");

        $response->assertRedirect(route('organizations.index'));
        $this->assertSoftDeleted('organizations', ['id' => $orgToDelete->id]);
    }

    public function test_member_cannot_delete_organization(): void
    {
        $response = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->delete("/organizations/{$this->primaryOrganization->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('organizations', ['id' => $this->primaryOrganization->id]);
    }

    public function test_outsider_cannot_delete_foreign_organization(): void
    {
        $response = $this->actingAs($this->outsider)
            ->withSession(['current_organization_id' => $this->secondaryOrganization->id])
            ->delete("/organizations/{$this->primaryOrganization->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('organizations', ['id' => $this->primaryOrganization->id]);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/organizations', [
                'name' => '',
                'country' => 'CHE',
                'currency' => 'TOOLONG',
                'locale' => 'xx',
                'chart_of_accounts' => 'unknown_template',
            ]);

        $response->assertSessionHasErrors(['name', 'country', 'currency', 'locale', 'chart_of_accounts']);
        $this->assertDatabaseMissing('organizations', ['name' => '']);
    }

    public function test_owner_can_update_organization_and_member_cannot(): void
    {
        $ownerResponse = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->put('/settings/general', [
                'name' => 'Primary Org Updated',
                'legal_name' => 'Primary Org Holdings',
                'address' => 'New Street 5',
                'city' => 'Bern',
                'postal_code' => '3000',
                'canton' => 'BE',
                'country' => 'CH',
                'vat_number' => 'CHE-999.888.777',
                'currency' => 'CHF',
                'locale' => 'de',
            ]);

        $ownerResponse->assertRedirect(route('settings'));
        $this->assertDatabaseHas('organizations', [
            'id' => $this->primaryOrganization->id,
            'name' => 'Primary Org Updated',
            'city' => 'Bern',
            'country' => 'CH',
            'locale' => 'de',
        ]);

        $memberResponse = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->put('/settings/general', [
                'name' => 'Member Attempt',
                'legal_name' => 'Member Attempt',
                'address' => null,
                'city' => null,
                'postal_code' => null,
                'canton' => null,
                'country' => 'CH',
                'vat_number' => null,
                'currency' => 'CHF',
                'locale' => 'en',
            ]);

        $memberResponse->assertForbidden();
        $this->assertDatabaseMissing('organizations', [
            'id' => $this->primaryOrganization->id,
            'name' => 'Member Attempt',
        ]);
    }

    public function test_owner_can_request_a_fiscal_year_change_without_changing_the_current_setting(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/settings/fiscal-year-change-request', [
                'requested_start' => '04-01',
            ]);

        $response->assertRedirect(route('settings'));
        $this->assertSame('01-01', $this->primaryOrganization->fresh()->fiscal_year_start);
        $this->assertDatabaseHas('fiscal_year_change_requests', [
            'organization_id' => $this->primaryOrganization->id,
            'current_start' => '01-01',
            'requested_start' => '04-01',
            'status' => 'pending',
        ]);
    }

    public function test_member_cannot_request_a_fiscal_year_change(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/settings/fiscal-year-change-request', [
                'requested_start' => '04-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('fiscal_year_change_requests', 0);
    }

    public function test_repeated_fiscal_year_change_request_updates_the_pending_request(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/settings/fiscal-year-change-request', ['requested_start' => '04-01'])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/settings/fiscal-year-change-request', ['requested_start' => '07-01'])
            ->assertRedirect();

        $this->assertDatabaseCount('fiscal_year_change_requests', 1);
        $this->assertDatabaseHas('fiscal_year_change_requests', [
            'requested_start' => '07-01',
            'status' => 'pending',
        ]);
    }

    public function test_fiscal_year_change_rejects_impossible_calendar_dates(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/settings/fiscal-year-change-request', [
                'requested_start' => '02-31',
            ])
            ->assertSessionHasErrors('requested_start');

        $this->assertDatabaseCount('fiscal_year_change_requests', 0);
    }

    public function test_fiscal_year_change_effective_year_uses_the_requested_start_date(): void
    {
        Carbon::setTestNow('2026-08-27');
        $this->primaryOrganization->update(['fiscal_year_start' => '10-01']);

        try {
            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->primaryOrganization->id])
                ->post('/settings/fiscal-year-change-request', [
                    'requested_start' => '04-01',
                ])
                ->assertRedirect();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertDatabaseHas('fiscal_year_change_requests', [
            'effective_year' => 2027,
        ]);
    }

    public function test_owner_can_approve_and_apply_a_fiscal_year_change_at_its_effective_date(): void
    {
        Carbon::setTestNow('2026-08-27');
        $this->primaryOrganization->update(['fiscal_year_start' => '10-01']);

        try {
            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->primaryOrganization->id])
                ->post('/settings/fiscal-year-change-request', ['requested_start' => '04-01'])
                ->assertRedirect();

            $request = FiscalYearChangeRequest::query()->firstOrFail();

            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->primaryOrganization->id])
                ->post(route('settings.fiscal-year-change.approve', $request))
                ->assertRedirect(route('settings'));

            $this->assertSame('approved', $request->fresh()->status->value);
            $this->assertSame('10-01', $this->primaryOrganization->fresh()->fiscal_year_start);

            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->primaryOrganization->id])
                ->post(route('settings.fiscal-year-change.apply', $request))
                ->assertSessionHasErrors('fiscal_year_change');

            Carbon::setTestNow('2027-04-01');

            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->primaryOrganization->id])
                ->post(route('settings.fiscal-year-change.apply', $request))
                ->assertRedirect(route('settings'));
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('applied', $request->fresh()->status->value);
        $this->assertSame('04-01', $this->primaryOrganization->fresh()->fiscal_year_start);
    }

    public function test_member_cannot_approve_a_fiscal_year_change(): void
    {
        $request = FiscalYearChangeRequest::create([
            'organization_id' => $this->primaryOrganization->id,
            'requested_by_user_id' => $this->owner->id,
            'current_start' => '01-01',
            'requested_start' => '04-01',
            'effective_year' => 2027,
            'status' => 'pending',
        ]);

        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post(route('settings.fiscal-year-change.approve', $request))
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status->value);
    }

    public function test_owner_can_reject_a_fiscal_year_change_without_changing_the_setting(): void
    {
        $request = FiscalYearChangeRequest::create([
            'organization_id' => $this->primaryOrganization->id,
            'requested_by_user_id' => $this->owner->id,
            'current_start' => '01-01',
            'requested_start' => '04-01',
            'effective_year' => 2027,
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post(route('settings.fiscal-year-change.reject', $request))
            ->assertRedirect(route('settings'));

        $this->assertSame('rejected', $request->fresh()->status->value);
        $this->assertSame('01-01', $this->primaryOrganization->fresh()->fiscal_year_start);
    }
}
