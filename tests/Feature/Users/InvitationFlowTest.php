<?php

namespace Tests\Feature\Users;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Notifications\InvitationNotification;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class InvitationFlowTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->owner = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    public function test_invitation_creates_record_and_sends_notification(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'invited@example.com',
                'role' => 'member',
            ]);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $this->organization->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'invited_by' => $this->owner->id,
        ]);

        Notification::assertSentOnDemand(InvitationNotification::class);
    }

    public function test_invitation_notification_uses_organization_locale(): void
    {
        Notification::fake();

        $this->organization->update(['locale' => 'fr']);

        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations", [
                'email' => 'localized@example.com',
                'role' => 'member',
            ]);

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            function (InvitationNotification $notification, array $channels, object $notifiable) {
                return $notification->locale === 'fr';
            },
        );
    }

    public function test_accept_invitation_for_existing_user(): void
    {
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

        $plainToken = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($invitedUser)
            ->get("/invitations/{$plainToken}/accept");

        $response->assertRedirect('/');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $invitedUser->id,
            'role' => 'member',
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_unauthenticated_accept_redirects_to_login(): void
    {
        $plainToken = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'new@example.com',
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get("/invitations/{$plainToken}/accept");

        $response->assertRedirect('/login');
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $invitedUser = User::factory()->create(['email' => 'expired@example.com']);

        $plainToken = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'invited_by' => $this->owner->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($invitedUser)
            ->get("/invitations/{$plainToken}/accept");

        $response->assertSessionHasErrors('token');
    }

    public function test_cancel_invitation(): void
    {
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'cancel@example.com',
            'role' => 'member',
            'token' => Str::random(64),
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->delete("/organizations/{$this->organization->id}/invitations/{$invitation->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('organization_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_resend_invitation(): void
    {
        Notification::fake();

        $oldPlainToken = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'resend@example.com',
            'role' => 'member',
            'token' => hash('sha256', $oldPlainToken),
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post("/organizations/{$this->organization->id}/invitations/{$invitation->id}/resend");

        $response->assertRedirect();

        $freshInvitation = $invitation->fresh();
        $this->assertNotEquals(hash('sha256', $oldPlainToken), $freshInvitation->token);

        Notification::assertSentOnDemand(InvitationNotification::class);
    }
}
