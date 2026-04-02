<?php

namespace Tests\Feature\Api;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);

        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  Personal Token Management (Web UI endpoints)
    // ──────────────────────────────────────────────────────────────

    public function test_token_settings_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/api-tokens')
            ->assertStatus(200);
    }

    public function test_create_personal_token(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/api-tokens/personal', [
                'name' => 'My Token',
                'abilities' => ['customers:read', 'invoices:read'],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'name' => 'My Token',
        ]);
    }

    public function test_delete_personal_token(): void
    {
        $sanctumToken = $this->user->createToken('deleteme', ['*']);
        $sanctumToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);

        $this->actingAs($this->user)
            ->delete("/settings/api-tokens/personal/{$sanctumToken->accessToken->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $sanctumToken->accessToken->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Webhook Settings (Web UI endpoints)
    // ──────────────────────────────────────────────────────────────

    public function test_webhook_settings_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/webhooks')
            ->assertStatus(200);
    }

    public function test_create_webhook(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['invoice.created', 'invoice.updated'],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('webhooks', [
            'organization_id' => $this->org->id,
            'url' => 'https://example.com/webhook',
        ]);
    }

    public function test_create_webhook_validates_url(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/webhooks', [
                'url' => 'not-a-url',
                'events' => ['invoice.created'],
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_delete_webhook(): void
    {
        $webhook = Webhook::create([
            'organization_id' => $this->org->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['invoice.created'],
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->delete("/settings/webhooks/{$webhook->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_unauthenticated_cannot_access_token_settings(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect('/login');
    }

    public function test_unauthenticated_cannot_access_webhook_settings(): void
    {
        $this->get('/settings/webhooks')->assertRedirect('/login');
    }
}
