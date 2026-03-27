<?php

namespace Tests\Security\Api;

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

    #[DataProvider('validExternalUrls')]
    public function test_webhook_creation_accepts_valid_external_url(string $url): void
    {
        // A valid external URL should pass the SSRF check (may fail for other reasons
        // like DNS resolution, but must not return 422 for the URL validation itself)
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $response = $this->withToken($token)
            ->postJson('/api/v1/webhooks', [
                'url' => $url,
                'events' => ['invoice.created'],
            ]);

        // 422 is only acceptable if it's NOT about the URL field specifically failing SSRF check
        // We accept 200/201 (created), or 422 with errors on fields OTHER than url's network check
        if ($response->status() === 422) {
            $errors = $response->json('errors.url', []);
            $ssrfErrors = array_filter($errors, fn ($e) => str_contains(strtolower($e), 'private') || str_contains(strtolower($e), 'internal'));
            $this->assertEmpty($ssrfErrors, "Valid external URL [{$url}] should not be rejected by SSRF validator");
        }
    }

    public static function validExternalUrls(): array
    {
        return [
            'https external' => ['https://webhook.example.com/endpoint'],
            'http external' => ['http://webhook.example.com/endpoint'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Webhook update — SSRF check must also apply on PUT
    // ──────────────────────────────────────────────────────────────

    public function test_webhook_update_also_validates_ssrf(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        // First create a valid webhook
        $create = $this->withToken($token)
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://webhook.example.com/legit',
                'events' => ['invoice.created'],
            ]);

        if ($create->status() !== 201 && $create->status() !== 200) {
            $this->markTestSkipped('Could not create webhook to test update SSRF');
        }

        $webhookId = $create->json('data.id') ?? $create->json('id');

        // Attempt to update the URL to an SSRF target
        $this->withToken($token)
            ->putJson("/api/v1/webhooks/{$webhookId}", [
                'url' => 'http://169.254.169.254/latest/meta-data/',
                'events' => ['invoice.created'],
            ])
            ->assertStatus(422);
    }
}
