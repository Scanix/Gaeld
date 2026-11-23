<?php

namespace Tests\Security\Api;

use App\Domains\Api\Models\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Security\SecurityTestCase;

/**
 * SSRF (Server-Side Request Forgery) protection tests for the webhook URL validator.
 *
 * The ValidWebhookUrl rule must reject URLs pointing at internal/private network
 * addresses. An attacker must not be able to use the webhook delivery system to
 * probe internal infrastructure.
 */
class WebhookSsrfTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['features.api_access' => true]);
    }

    #[DataProvider('ssrfPayloads')]
    public function test_webhook_creation_rejects_internal_url(string $url, string $description): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $response = $this->withToken($token)
            ->postJson('/api/v1/webhooks', [
                'url' => $url,
                'events' => ['invoice.created'],
            ]);

        $this->assertContains(
            $response->status(),
            [422, 403],
            "Webhook with SSRF URL [{$description}] should be rejected. Got HTTP {$response->status()}"
        );
    }

    public static function ssrfPayloads(): array
    {
        return [
            'localhost' => ['http://localhost/',                       'localhost'],
            '127.0.0.1' => ['http://127.0.0.1/',                       'IPv4 loopback'],
            '127.0.0.1 with port' => ['http://127.0.0.1:8080/internal',          'IPv4 loopback with port'],
            'IPv6 loopback' => ['http://[::1]/',                           'IPv6 loopback'],
            'RFC1918 10.x' => ['http://10.0.0.1/',                        'RFC1918 10.x'],
            'RFC1918 172.16.x' => ['http://172.16.0.1/',                      'RFC1918 172.16.x'],
            'RFC1918 192.168.x' => ['http://192.168.1.1/',                     'RFC1918 192.168.x'],
            'AWS metadata' => ['http://169.254.169.254/latest/meta-data/', 'AWS metadata endpoint'],
            'GCP metadata' => ['http://metadata.google.internal/',         'GCP metadata endpoint'],
            '0.0.0.0' => ['http://0.0.0.0/',                         '0.0.0.0'],
            'Redis port' => ['http://127.0.0.1:6379/',                  'Redis default port'],
            'PostgreSQL port' => ['http://127.0.0.1:5432/',                  'PostgreSQL default port'],
        ];
    }

    #[DataProvider('externalUrls')]
    public function test_webhook_creation_validates_external_url(string $url, int $expectedStatus): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);
        $response = $this->withToken($token)
            ->postJson('/api/v1/webhooks', [
                'url' => $url,
                'events' => ['invoice.created'],
            ])
            ->assertStatus($expectedStatus);

        if ($expectedStatus === 422) {
            $response->assertJsonValidationErrors('url');

            return;
        }

        $response->assertJsonPath('data.url', $url);
        $this->assertDatabaseHas('webhooks', ['id' => $response->json('data.id'), 'organization_id' => $this->orgA->id]);
    }

    public static function externalUrls(): array
    {
        return [
            'public HTTPS address' => ['https://93.184.216.34/endpoint', 201],
            'external HTTP address' => ['http://example.com/endpoint', 422],
            'unresolvable HTTPS hostname' => ['https://webhook.invalid/endpoint', 422],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Webhook update — SSRF check must also apply on PUT
    // ──────────────────────────────────────────────────────────────

    public function test_webhook_update_also_validates_ssrf(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $webhook = Webhook::create([
            'organization_id' => $this->orgA->id,
            'url' => 'https://webhook.example.com/legit',
            'secret' => Webhook::generateSecret(),
            'events' => ['invoice.created'],
            'is_active' => true,
        ]);

        // Attempt to update the URL to an SSRF target
        $this->withToken($token)
            ->putJson("/api/v1/webhooks/{$webhook->id}", [
                'url' => 'http://169.254.169.254/latest/meta-data/',
                'events' => ['invoice.created'],
            ])
            ->assertStatus(422);
    }
}
