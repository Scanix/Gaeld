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

        $user = User::factory()->create();
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

        $response->assertForbidden();
    }

    public function test_stale_session_org_id_falls_back_to_first_membership(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
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
