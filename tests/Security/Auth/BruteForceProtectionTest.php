<?php

namespace Tests\Security\Auth;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Tests\Security\SecurityTestCase;

/**
 * Verifies that rate limiting is enforced on authentication endpoints.
 * An attacker must receive HTTP 429 after exceeding the allowed attempts.
 */
class BruteForceProtectionTest extends SecurityTestCase
{
    // ──────────────────────────────────────────────────────────────
    //  Login — throttle:5,1
    // ──────────────────────────────────────────────────────────────

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        // Create an org so the login controller does not bail early
        Organization::create(['name' => 'Throttle Org', 'currency' => 'CHF']);

        $payload = ['email' => 'brute@example.com', 'password' => 'wrongpassword'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $payload); // May return 302 redirect or 422
        }

        $this->post('/login', $payload)
            ->assertTooManyRequests();
    }

    // ──────────────────────────────────────────────────────────────
    //  Two-factor challenge — throttle:5,1
    // ──────────────────────────────────────────────────────────────

    public function test_two_factor_challenge_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create();
        $payload = ['code' => '000000'];

        // Pre-load the session as if we just completed the password step
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['two_factor:user_id' => $user->id])
                ->post('/two-factor-challenge', $payload);
        }

        $this->withSession(['two_factor:user_id' => $user->id])
            ->post('/two-factor-challenge', $payload)
            ->assertTooManyRequests();
    }

    // ──────────────────────────────────────────────────────────────
    //  Password reset — throttle:3,1
    // ──────────────────────────────────────────────────────────────

    public function test_password_reset_link_is_throttled_after_three_attempts(): void
    {
        $payload = ['email' => 'reset@example.com'];

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', $payload);
        }

        $this->post('/forgot-password', $payload)
            ->assertTooManyRequests();
    }

    // ──────────────────────────────────────────────────────────────
    //  Passkey login — throttle:5,1
    // ──────────────────────────────────────────────────────────────

    public function test_passkey_login_is_throttled_after_five_attempts(): void
    {
        // Wrong body triggers validation failure — still counts toward rate limit
        $payload = ['invalid' => 'garbage'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/passkey/login', $payload);
        }

        $this->postJson('/passkey/login', $payload)
            ->assertTooManyRequests();
    }

    // ──────────────────────────────────────────────────────────────
    //  REST API — throttle:api (60/min by default)
    //  Verify the middleware is wired up — spot-check with headers
    // ──────────────────────────────────────────────────────────────

    public function test_api_throttle_header_is_present_on_authenticated_request(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $response = $this->withToken($token)->getJson('/api/v1/customers');

        // X-RateLimit-Limit indicates the throttle middleware is active
        $response->assertHeader('X-RateLimit-Limit');
    }
}
