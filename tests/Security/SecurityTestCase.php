<?php

namespace Tests\Security;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

/**
 * Base class for all security tests.
 *
 * Sets up two isolated organizations (Org A and Org B) with owner accounts,
 * ready for IDOR, RBAC, and cross-tenant isolation tests.
 */
abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $ownerA;

    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush cache between tests so rate limiters start clean.
        Cache::flush();

        $this->seedPermissions();

        $this->ownerA = User::factory()->create();
        $this->ownerB = User::factory()->create();

        $this->orgA = Organization::create(['name' => 'Security Org A', 'currency' => 'CHF']);
        $this->orgB = Organization::create(['name' => 'Security Org B', 'currency' => 'CHF']);

        $this->orgA->users()->attach($this->ownerA->id, ['role' => 'owner']);
        $this->orgB->users()->attach($this->ownerB->id, ['role' => 'owner']);

        $this->assignOrganizationRole($this->ownerA, $this->orgA, 'owner');
        $this->assignOrganizationRole($this->ownerB, $this->orgB, 'owner');

        $this->ensureSubscriptionIfSaas($this->orgA);
        $this->ensureSubscriptionIfSaas($this->orgB);
    }

    /**
     * Assert that a response is a security denial.
     * 403 = explicit policy rejection.
     * 404 = resource hidden by BelongsToOrganization global scope (also acceptable).
     * Both protect the user from accessing cross-org data.
     */
    protected function assertDenied(TestResponse $response): void
    {
        $this->assertContains(
            $response->status(),
            [403, 404],
            "Expected access denied (403 or 404) but got HTTP {$response->status()}"
        );
    }

    /**
     * Create a Sanctum API token scoped to the given org for the given user.
     */
    protected function createApiToken(User $user, Organization $org): string
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($org);

        $result = $user->createToken('security-test-token', ['*']);
        $result->accessToken->update([
            'organization_id' => $org->id,
            'type' => TokenType::Personal,
        ]);

        return $result->plainTextToken;
    }

    /**
     * Create a member user attached to the given org with the given role.
     */
    protected function createUserInOrg(Organization $org, string $role = 'member'): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role]);
        $this->assignOrganizationRole($user, $org, $role);

        return $user;
    }
}
