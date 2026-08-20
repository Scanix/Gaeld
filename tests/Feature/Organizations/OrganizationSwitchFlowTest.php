<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class OrganizationSwitchFlowTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    public function test_switch_route_updates_active_organization_in_session(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        /** @var User $user */
        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $orgA->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $orgA, 'owner');
        $orgB->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $orgB, 'owner');
        $this->ensureSubscriptionIfSaas($orgA);
        $this->ensureSubscriptionIfSaas($orgB);

        $switch = $this->actingAs($user)
            ->withSession(['current_organization_id' => $orgA->id])
            ->post("/organizations/{$orgB->id}/switch");

        $switch->assertRedirect('/dashboard');
        $switch->assertSessionHas('current_organization_id', $orgB->id);

    }

    public function test_switch_route_forbids_unrelated_organization(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        /** @var User $user */
        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $orgA->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $orgA, 'owner');
        $this->ensureSubscriptionIfSaas($orgA);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $orgA->id])
            ->post("/organizations/{$orgB->id}/switch");

        // 404, not 403: an existing-but-unrelated org must be indistinguishable
        // from a nonexistent one, otherwise the response leaks org existence.
        $response->assertNotFound();
    }

    public function test_switch_route_returns_not_found_for_nonexistent_organization(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        /** @var User $user */
        $org = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $org, 'owner');
        $this->ensureSubscriptionIfSaas($org);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->post('/organizations/'.fake()->uuid().'/switch');

        $response->assertNotFound();
    }

    public function test_switch_route_returns_not_found_for_malformed_organization_id(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        /** @var User $user */
        $org = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $org, 'owner');
        $this->ensureSubscriptionIfSaas($org);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->post('/organizations/not-a-uuid/switch');

        $response->assertNotFound();
    }

    public function test_unverified_user_gets_same_response_for_existing_and_nonexistent_organization(): void
    {
        $this->seedPermissions();

        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        /** @var User $unverifiedUser */
        $organization = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $organization->users()->attach($unverifiedUser->id, ['role' => 'owner']);
        $this->assignOrganizationRole($unverifiedUser, $organization, 'owner');

        $existing = $this->actingAs($unverifiedUser)
            ->post("/organizations/{$organization->id}/switch");
        $nonexistent = $this->actingAs($unverifiedUser)
            ->post('/organizations/'.fake()->uuid().'/switch');

        // Both must be redirected to the verification gate identically — an
        // unverified user must not be able to tell a real org id from a fake
        // one by comparing response codes.
        $existing->assertRedirect(route('verification.notice'));
        $nonexistent->assertRedirect(route('verification.notice'));
    }

    public function test_stale_session_org_id_falls_back_to_first_membership(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        /** @var User $user */
        $organization = Organization::create(['name' => 'Fallback Org', 'currency' => 'CHF']);
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');
        $this->ensureSubscriptionIfSaas($organization);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => (string) fake()->uuid()])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.currentOrganization.id', $organization->id)
            ->where('auth.currentOrganization.name', 'Fallback Org'));
        $response->assertSessionMissing('current_organization_id');
    }
}
