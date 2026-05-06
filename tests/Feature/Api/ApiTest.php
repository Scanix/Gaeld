<?php

namespace Tests\Feature\Api;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\Webhook;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ApiTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);

        $this->setUpOrganization();

        // Create a personal Sanctum token for API tests
        $sanctumToken = $this->user->createToken('test-token', ['*']);
        $sanctumToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);
        $this->token = $sanctumToken->plainTextToken;
    }

    // ──────────────────────────────────────────────────────────────
    //  Authentication
    // ──────────────────────────────────────────────────────────────

    public function test_api_root_returns_version_info(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonStructure(['name', 'version', 'documentation', 'status'])
            ->assertJson(['version' => 'v1', 'status' => 'ok']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/customers')
            ->assertStatus(401);
    }

    public function test_feature_flag_disabled_returns_403(): void
    {
        config(['features.api_access' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/customers')
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    //  Multi-Organization — Personal Tokens
    // ──────────────────────────────────────────────────────────────

    public function test_personal_token_scoped_to_one_org(): void
    {
        // User belongs to two orgs
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'CHF']);
        $orgB->users()->attach($this->user->id, ['role' => 'member']);

        // Token is for org A — should only see org A's data
        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Org A Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $customerB = new Customer;
        $customerB->organization_id = $orgB->id;
        $customerB->name = 'Org B Customer';
        $customerB->country = 'CH';
        $customerB->currency = 'CHF';
        $customerB->saveQuietly();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/customers')
            ->assertOk();

        // Only org A customer visible
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Org A Customer', $names);
        $this->assertNotContains('Org B Customer', $names);
    }

    public function test_personal_token_fails_when_user_removed_from_org(): void
    {
        // Remove user from org
        $this->org->users()->detach($this->user->id);

        $this->withToken($this->token)
            ->getJson('/api/v1/customers')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Organization not found or access denied.');
    }

    public function test_user_can_have_tokens_for_different_orgs(): void
    {
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'CHF']);
        $orgB->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $orgB, 'owner');

        // Create separate token for org B
        $tokenB = $this->user->createToken('org-b-token', ['*']);
        $tokenB->accessToken->update([
            'organization_id' => $orgB->id,
            'type' => TokenType::Personal,
        ]);

        Contact::create([
            'organization_id' => $orgB->id,
            'name' => 'Org B Only',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        // Token B should access org B data
        $response = $this->withToken($tokenB->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Org B Only', $names);
    }

    // ──────────────────────────────────────────────────────────────
    //  Organization Tokens
    // ──────────────────────────────────────────────────────────────

    public function test_create_organization_token(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/org-tokens', [
                'name' => 'CI/CD Integration',
                'abilities' => [Permission::InvoicingView->value, Permission::ContactsView->value],
                'expires_in_days' => 90,
            ])
            ->assertStatus(201)
            ->assertJsonPath('type', 'organization')
            ->assertJsonStructure(['token', 'name', 'type', 'abilities', 'expires_at']);
    }

    public function test_list_organization_tokens(): void
    {
        // Create an org token
        $orgToken = $this->user->createToken('Org Token', ['*']);
        $orgToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Organization,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/org-tokens')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Org Token', $data[0]['name']);
        $this->assertArrayHasKey('created_by', $data[0]);
    }

    public function test_org_token_not_in_personal_token_list(): void
    {
        // Create an org token
        $orgToken = $this->user->createToken('Org Token', ['*']);
        $orgToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Organization,
        ]);

        // Personal token list should NOT include org tokens
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/tokens')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertNotContains('Org Token', $names);
    }

    public function test_delete_organization_token(): void
    {
        $orgToken = $this->user->createToken('To Delete', ['*']);
        $orgToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Organization,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/org-tokens/{$orgToken->accessToken->uuid}")
            ->assertStatus(204);
    }

    public function test_org_token_survives_creator_leaving_org(): void
    {
        // Admin creates an org token
        $orgToken = $this->user->createToken('Persistent Token', ['*']);
        $orgToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Organization,
        ]);

        // Admin leaves the org
        $this->org->users()->detach($this->user->id);

        // Org token should still work (no user membership check)
        $this->withToken($orgToken->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertOk();
    }

    public function test_member_cannot_create_org_token(): void
    {
        $member = User::factory()->create();
        $this->org->users()->attach($member->id, ['role' => 'member']);
        $this->assignOrganizationRole($member, $this->org, 'member');

        $memberToken = $member->createToken('member-token', ['*']);
        $memberToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);

        $this->withToken($memberToken->plainTextToken)
            ->postJson('/api/v1/org-tokens', [
                'name' => 'Should Fail',
                'abilities' => ['*'],
            ])
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    //  Customers
    // ──────────────────────────────────────────────────────────────

    public function test_list_customers(): void
    {
        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme AG',
            'email' => 'info@acme.ch',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'currency', 'created_at'],
                ],
            ]);
    }

    public function test_create_customer(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/customers', [
                'name' => 'New Customer AG',
                'email' => 'hello@customer.ch',
                'country' => 'CH',
                'currency' => 'CHF',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'New Customer AG');

        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->org->id,
            'name' => 'New Customer AG',
        ]);
    }

    public function test_show_customer(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Me AG',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/customers/{$customer->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Me AG');
    }

    public function test_update_customer(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/customers/{$customer->uuid}", [
                'name' => 'New Name',
                'country' => 'CH',
                'currency' => 'CHF',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_delete_customer(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Me AG',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/customers/{$customer->uuid}")
            ->assertStatus(204);
    }

    // ──────────────────────────────────────────────────────────────
    //  Tenant Isolation
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_access_other_org_data(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'CHF']);

        // Create customer in another org (bypass global scope)
        $customer = new Customer;
        $customer->organization_id = $otherOrg->id;
        $customer->name = 'Secret Customer';
        $customer->country = 'CH';
        $customer->currency = 'CHF';
        $customer->saveQuietly();

        $this->withToken($this->token)
            ->getJson("/api/v1/customers/{$customer->uuid}")
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  Personal Token Management
    // ──────────────────────────────────────────────────────────────

    public function test_list_tokens(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_create_token(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/tokens', [
                'name' => 'My new token',
                'abilities' => [Permission::ContactsView->value, Permission::InvoicingView->value],
                'expires_in_days' => 30,
            ])
            ->assertStatus(201)
            ->assertJsonPath('type', 'personal')
            ->assertJsonStructure(['token', 'name', 'type', 'abilities', 'expires_at']);
    }

    public function test_create_token_rejects_invalid_abilities(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/tokens', [
                'name' => 'Bad token',
                'abilities' => ['customers:read'],
            ])
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────
    //  Permission Enforcement via Token Abilities
    // ──────────────────────────────────────────────────────────────

    public function test_personal_token_limited_by_abilities(): void
    {
        // Create a token that only has contacts.view
        $limited = $this->user->createToken('limited', [Permission::ContactsView->value]);
        $limited->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);

        // Reading customers should work
        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Readable AG',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($limited->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertOk();

        // Creating a customer should be denied (no contacts.create ability)
        $this->withToken($limited->plainTextToken)
            ->postJson('/api/v1/customers', [
                'name' => 'Should Fail',
                'country' => 'CH',
                'currency' => 'CHF',
            ])
            ->assertStatus(403);
    }

    public function test_org_token_limited_by_abilities(): void
    {
        // Org token with only contacts.view
        $orgToken = $this->user->createToken('org-limited', [Permission::ContactsView->value]);
        $orgToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Organization,
        ]);

        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Readable AG',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($orgToken->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertOk();

        $this->withToken($orgToken->plainTextToken)
            ->postJson('/api/v1/customers', [
                'name' => 'Should Fail',
                'country' => 'CH',
                'currency' => 'CHF',
            ])
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    //  Webhooks
    // ──────────────────────────────────────────────────────────────

    public function test_create_webhook(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['invoice.created', 'customer.updated'],
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'url', 'events', 'is_active'],
                'secret',
            ]);

        $this->assertDatabaseHas('webhooks', [
            'organization_id' => $this->org->id,
            'url' => 'https://example.com/webhook',
        ]);
    }

    public function test_list_webhooks(): void
    {
        Webhook::create([
            'organization_id' => $this->org->id,
            'url' => 'https://example.com/hook1',
            'secret' => str_repeat('a', 64),
            'events' => ['invoice.created'],
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_webhook_invalid_event_rejected(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['invalid.event'],
            ])
            ->assertStatus(422);
    }

    public function test_regenerate_webhook_secret(): void
    {
        $webhook = Webhook::create([
            'organization_id' => $this->org->id,
            'url' => 'https://example.com/hook',
            'secret' => str_repeat('b', 64),
            'events' => ['invoice.created'],
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/webhooks/{$webhook->id}/regenerate-secret")
            ->assertOk()
            ->assertJsonStructure(['secret']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Meta endpoints
    // ──────────────────────────────────────────────────────────────

    public function test_list_abilities(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/meta/abilities')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_list_webhook_events(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/meta/webhook-events')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
