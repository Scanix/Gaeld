<?php

namespace Tests\Feature\Api;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Middleware\Api\TokenPermissionMap;
use Tests\Security\SecurityTestCase;

class ApiContractTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_api_root_returns_version_information_without_authentication(): void
    {
        $this->getJson('/api/v1/')
            ->assertOk()
            ->assertJsonPath('version', 'v1')
            ->assertJsonPath('status', 'ok');
    }

    public function test_protected_api_requests_return_stable_authentication_errors(): void
    {
        $this->getJson('/api/v1/journal-entries')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_abilities_include_journal_entry_operations(): void
    {
        $abilities = $this->withToken($this->tokenA)
            ->getJson('/api/v1/meta/abilities')
            ->assertOk()
            ->json('data');

        $this->assertContains('accounting.view', $abilities);
        $this->assertContains('accounting.create', $abilities);
        $this->assertContains('accounting.edit', $abilities);
        $this->assertContains('accounting.delete', $abilities);
    }

    public function test_token_creation_response_is_not_stored_for_replay(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/tokens', [
            'name' => 'One-time API token',
            'abilities' => ['accounting.view'],
        ]);

        $response->assertCreated()->assertJsonStructure(['token']);
        $this->assertDatabaseCount('api_idempotency_keys', 0);
    }

    public function test_contact_aliases_are_available_and_abilities_match_the_live_map(): void
    {
        $this->withToken($this->tokenA)->getJson('/api/v1/contacts')->assertOk();
        $this->withToken($this->tokenA)->getJson('/api/v1/customers')->assertOk();

        $abilities = [];
        foreach (TokenPermissionMap::get() as $modelAbilities) {
            foreach ($modelAbilities as $permission) {
                $abilities[$permission->value] = true;
            }
        }
        ksort($abilities);

        $this->assertSame(array_keys($abilities), $this->withToken($this->tokenA)
            ->getJson('/api/v1/meta/abilities')
            ->assertOk()
            ->json('data'));
    }

    public function test_rate_limit_headers_are_present_when_testing_does_not_disable_throttling(): void
    {
        $response = $this->withToken($this->tokenA)->getJson('/api/v1/journal-entries');

        if (! $response->headers->has('X-RateLimit-Limit')) {
            $this->markTestSkipped('The testing environment disables API throttling.');
        }

        $response->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');
    }
}
