<?php

namespace Tests\Security\Api;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Tests\Security\SecurityTestCase;

/**
 * Verifies API token security:
 *
 * - A token scoped to Org A cannot list or fetch Org B resources
 * - Expired tokens are rejected (401)
 * - Revoked/deleted tokens are rejected (401)
 * - A removed member's token stops working (open issue M-10 — documented)
 */
class ApiTokenSecurityTest extends SecurityTestCase
{
    private string $tokenA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);

        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);

        // Create a customer in Org B to use as cross-org target
        app(CurrentOrganization::class)->set($this->orgB);
        $this->customerB = Contact::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Secret Customer',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  No auth
    // ──────────────────────────────────────────────────────────────

    public function test_request_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $this->withToken('invalid-token-string')
            ->getJson('/api/v1/customers')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────
    //  Expired token
    // ──────────────────────────────────────────────────────────────

    public function test_expired_token_is_rejected(): void
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

        $result = $this->ownerA->createToken('expired-token', ['*']);
        $result->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
            'expires_at' => now()->subMinute(), // Already expired
        ]);

        $this->withToken($result->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────
    //  Revoked token
    // ──────────────────────────────────────────────────────────────

    public function test_deleted_token_is_rejected(): void
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

        $result = $this->ownerA->createToken('soon-deleted', ['*']);
        $result->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);
        $plainText = $result->plainTextToken;

        // Delete the token
        $result->accessToken->delete();

        $this->withToken($plainText)
            ->getJson('/api/v1/customers')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────
    //  Cross-org token isolation
    // ──────────────────────────────────────────────────────────────

    public function test_token_scoped_to_org_a_cannot_see_org_b_customers(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/customers');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains(
            (string) $this->customerB->id,
            $ids,
            'Org B customer must not appear in Org A token response'
        );
    }

    public function test_token_scoped_to_org_a_cannot_fetch_org_b_customer_by_id(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/customers/{$this->customerB->uuid}");

        $this->assertDenied($response); // 403 or 404 — both are valid security controls
    }

    // ──────────────────────────────────────────────────────────────
    //  Removed member's token — FIXED (was issue M-10)
    //  Token is now revoked when member is removed via MemberRemoved event.
    // ──────────────────────────────────────────────────────────────

    public function test_removed_member_api_token_is_invalidated(): void
    {
        // Create a member and their token
        $member = User::factory()->create();
        $this->orgA->users()->attach($member->id, ['role' => 'member']);
        $this->assignOrganizationRole($member, $this->orgA, 'member');

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

        $result = $member->createToken('member-token', ['*']);
        $result->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);
        $memberToken = $result->plainTextToken;

        // Verify it works before removal
        $this->withToken($memberToken)
            ->getJson('/api/v1/customers')
            ->assertOk();

        // Remove the member from the org
        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/organizations/{$this->orgA->id}/members/{$member->id}");

        // Token must be invalid after removal
        $response = $this->withToken($memberToken)->getJson('/api/v1/customers');

        // 401 (token revoked) or 403 (access denied) — both are acceptable security controls
        $this->assertContains($response->status(), [401, 403],
            "Expected 401 or 403 after member removal, got {$response->status()}");
    }
}
