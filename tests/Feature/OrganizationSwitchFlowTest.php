<?php

namespace Tests\Feature;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSwitchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_route_updates_active_organization_in_session(): void
    {
        $user = User::factory()->create();
        /** @var User $user */
        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $orgA->users()->attach($user->id, ['role' => 'owner']);
        $orgB->users()->attach($user->id, ['role' => 'owner']);

        $switch = $this->actingAs($user)
            ->withSession(['current_organization_id' => $orgA->id])
            ->post("/organizations/{$orgB->id}/switch");

        $switch->assertRedirect('/');
        $switch->assertSessionHas('current_organization_id', $orgB->id);

    }

    public function test_switch_route_forbids_unrelated_organization(): void
    {
        $user = User::factory()->create();
        /** @var User $user */
        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $orgA->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $orgA->id])
            ->post("/organizations/{$orgB->id}/switch");

        $response->assertForbidden();
    }

    public function test_stale_session_org_id_falls_back_to_first_membership(): void
    {
        $user = User::factory()->create();
        /** @var User $user */
        $organization = Organization::create(['name' => 'Fallback Org', 'currency' => 'CHF']);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => (string) fake()->uuid()])
            ->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.currentOrganization.id', $organization->id)
            ->where('auth.currentOrganization.name', 'Fallback Org'));
        $response->assertSessionMissing('current_organization_id');
    }
}