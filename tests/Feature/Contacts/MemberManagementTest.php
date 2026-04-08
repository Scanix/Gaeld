<?php

namespace Tests\Feature\Contacts;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Notifications\InvitationNotification;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private User $admin;

    private User $member;

    private User $outsider;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->organization->users()->attach($this->admin->id, ['role' => 'admin']);
        $this->assignOrganizationRole($this->admin, $this->organization, 'admin');

        $this->organization->users()->attach($this->member->id, ['role' => 'member']);
        $this->assignOrganizationRole($this->member, $this->organization, 'member');

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    // --- Invite Member ---

    public function test_owner_can_invite_member(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'newuser@example.com',
                'role' => 'member',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $this->organization->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
        ]);

        Notification::assertSentOnDemand(InvitationNotification::class);
    }

    public function test_admin_can_invite_member(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'another@example.com',
                'role' => 'member',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $this->organization->id,
            'email' => 'another@example.com',
        ]);
    }

    public function test_member_cannot_invite(): void
    {
        $response = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'newuser@example.com',
                'role' => 'member',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_invite_existing_member(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => $this->member->email,
                'role' => 'member',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_invite_as_owner(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'newuser@example.com',
                'role' => 'owner',
            ]);

        $response->assertForbidden();
    }

    // --- Change Role ---

    public function test_owner_can_change_member_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/members/{$this->member->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
            'role' => 'admin',
        ]);
    }

    public function test_member_cannot_change_roles(): void
    {
        $response = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/members/{$this->admin->id}/role", [
                'role' => 'viewer',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_change_last_owner_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/members/{$this->owner->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertSessionHasErrors('role');
    }

    // --- Remove Member ---

    public function test_owner_can_remove_member(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->delete("/organizations/{$this->organization->id}/members/{$this->member->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_member_cannot_remove_others(): void
    {
        $response = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->delete("/organizations/{$this->organization->id}/members/{$this->admin->id}");

        $response->assertForbidden();
    }

    public function test_cannot_remove_last_owner(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->delete("/organizations/{$this->organization->id}/members/{$this->owner->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
        ]);
    }

    // --- Leave Organization ---

    public function test_member_can_leave(): void
    {
        $response = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/leave");

        $response->assertRedirect('/organizations');

        $this->assertDatabaseMissing('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_last_owner_cannot_leave(): void
    {
        // Remove admin and member first, leaving only the owner
        $this->organization->users()->detach([$this->admin->id, $this->member->id]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/leave");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
        ]);
    }
}
