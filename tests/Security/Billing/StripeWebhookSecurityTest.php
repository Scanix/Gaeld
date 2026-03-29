<?php

namespace Tests\Security\Billing;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Route;
use Stripe\Webhook;
use Tests\Security\SecurityTestCase;

/**
 * Stripe webhook endpoint security tests.
 *
 * The /stripe/webhook endpoint must:
 * 1. Reject requests without a Stripe-Signature header (400)
 * 2. Reject requests with a tampered/invalid signature (400)
 * 3. Accept properly signed requests (handled by WebhookController)
 *
 * The endpoint intentionally has NO CSRF protection (Stripe cannot send a token).
 * Authentication is exclusively via the Stripe-Signature HMAC header.
 */
class StripeWebhookSecurityTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('stripe.webhook')) {
            $this->markTestSkipped('EE plugin not loaded — stripe webhook route unavailable');
        }
    }

    private function skipIfStripeNotInstalled(): void
    {
        if (! class_exists(Webhook::class)) {
            $this->markTestSkipped('stripe/stripe-php not installed — run: composer require stripe/stripe-php');
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Missing signature header
    // ──────────────────────────────────────────────────────────────

    public function test_request_without_stripe_signature_is_rejected(): void
    {
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => []],
        ]);

        $response = $this->postJson('/stripe/webhook', json_decode($payload, true));

        // A missing Stripe-Signature must result in a 400, not 200 or 500
        $this->assertContains(
            $response->status(),
            [400, 401, 403],
            'Webhook without Stripe-Signature must be rejected, got HTTP '.$response->status()
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Invalid / tampered signature
    // ──────────────────────────────────────────────────────────────

    public function test_request_with_invalid_stripe_signature_is_rejected(): void
    {
        $this->skipIfStripeNotInstalled();
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => []],
        ]);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=attacker_forged_signature_goes_here',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $this->assertContains(
            $response->status(),
            [400, 401, 403],
            'Webhook with invalid signature must be rejected, got HTTP '.$response->status()
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Replay attack — old timestamp outside tolerance window
    // ──────────────────────────────────────────────────────────────

    public function test_webhook_with_stale_timestamp_is_rejected(): void
    {
        $this->skipIfStripeNotInstalled();
        // Stripe rejects events whose timestamp is > 5 minutes old
        $staleTimestamp = time() - (6 * 60); // 6 minutes ago

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => []],
        ]);

        $fakeSignature = 't='.$staleTimestamp.',v1=fakehash';

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $fakeSignature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $this->assertContains(
            $response->status(),
            [400, 401, 403],
            'Stale webhook (replay attack) must be rejected'
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  SaaS admin — non-admin user must be blocked
    // ──────────────────────────────────────────────────────────────

    public function test_saas_admin_index_rejects_non_admin_email(): void
    {
        if (! Route::has('saas-admin.index')) {
            $this->markTestSkipped('saas-admin route not available');
        }

        // ownerA's email does NOT match SAAS_ADMIN_EMAIL config
        config(['services.saas_admin_email' => 'real-admin@gaeld.ch']);

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/saas-admin');

        // 302 redirect to /saas-admin/confirm or 403 — both deny access
        $this->assertContains($response->status(), [302, 403],
            "Non-admin user should be denied SaaS admin access, got HTTP {$response->status()}");
    }

    public function test_saas_admin_cannot_be_accessed_without_email_verification(): void
    {
        if (! Route::has('saas-admin.index')) {
            $this->markTestSkipped('saas-admin route not available');
        }

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->orgA->users()->attach($unverified->id, ['role' => 'member']);

        $response = $this->actingAs($unverified)
            ->get('/saas-admin');

        // 302 redirect to /email/verify or 403 — both deny access
        $this->assertContains($response->status(), [302, 403],
            "Unverified user should be denied SaaS admin access, got HTTP {$response->status()}");
    }
}
