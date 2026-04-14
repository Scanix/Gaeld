<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Security regression tests covering:
 * - Invitation token consumed by wrong user (Finding #4)
 * - Member role/remove on user not belonging to the org (Finding #5)
 */
class MemberSecurityTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  Finding #4 — Invitation accepted by wrong user
    // ──────────────────────────────────────────────────────────────

    public function test_invitation_accepted_by_wrong_user_returns_403(): void
    {
        $plainToken = Str::random(64);

        OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'victim@example.com',
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'invited_by' => $this->user->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Attacker is a different authenticated user
        $attacker = User::factory()->create(['email' => 'attacker@example.com']);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($attacker)
            ->get(route('invitations.accept', ['token' => $plainToken]))
            ->assertForbidden();
    }

    public function test_invitation_accepted_by_correct_user_succeeds(): void
    {
        $plainToken = Str::random(64);

        OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'invited_by' => $this->user->id,
            'expires_at' => now()->addDays(7),
        ]);

        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

        $this->actingAs($invitedUser)
            ->get(route('invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('dashboard'));
    }

    // ──────────────────────────────────────────────────────────────
    //  Finding #5 — Member role/remove without membership check
    // ──────────────────────────────────────────────────────────────

    public function test_member_role_change_on_non_member_returns_404(): void
    {
        // $outsider belongs to a different org, not $this->organization
        $outsider = User::factory()->create();
        $otherOrg = Organization::factory()->create();
        $otherOrg->users()->attach($outsider->id, ['role' => 'member']);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($this->user)
            ->post(route('organizations.members.updateRole', [
                'organization' => $this->organization,
                'user' => $outsider,
            ]), ['role' => 'admin'])
            ->assertNotFound();
    }

    public function test_member_remove_on_non_member_returns_404(): void
    {
        $outsider = User::factory()->create();
        $otherOrg = Organization::factory()->create();
        $otherOrg->users()->attach($outsider->id, ['role' => 'member']);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($this->user)
            ->delete(route('organizations.members.remove', [
                'organization' => $this->organization,
                'user' => $outsider,
            ]))
            ->assertNotFound();
    }
}
