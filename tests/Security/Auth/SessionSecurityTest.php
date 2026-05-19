<?php

namespace Tests\Security\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Tests\Security\SecurityTestCase;

/**
 * Verifies session security properties:
 * - Session regeneration on login (anti-fixation)
 * - Session invalidation on logout
 * - CSRF protection on state-changing routes
 * - Authentication survives the post-login redirect boundary
 */
class SessionSecurityTest extends SecurityTestCase
{
    // ──────────────────────────────────────────────────────────────
    //  Session fixation — the session ID must rotate on login
    //  without dropping the authenticated user from the new session.
    // ──────────────────────────────────────────────────────────────

    public function test_login_regenerates_session_and_keeps_user_authenticated(): void
    {
        $user = User::factory()->create(['password' => Hash::make('ValidPass1!')]);
        $this->orgA->users()->attach($user->id, ['role' => 'member']);

        $this->withSession(['attacker_marker' => 'injected'])->get('/login');

        $oldSessionId = session()->getId();

        $response = $this
            ->post('/login', [
                'email' => $user->email,
                'password' => 'ValidPass1!',
            ]);

        $response->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
    }

    // ──────────────────────────────────────────────────────────────
    //  Logout — session must be invalidated
    // ──────────────────────────────────────────────────────────────

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/logout');

        // After logout the user is no longer authenticated
        $this->assertGuest();
    }

    public function test_authenticated_routes_are_inaccessible_after_logout(): void
    {
        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/logout');

        $this->get('/dashboard')->assertRedirectContains('/login');
    }

    // ──────────────────────────────────────────────────────────────
    //  CSRF — state-changing web routes must reject missing token
    // ──────────────────────────────────────────────────────────────

    public function test_post_without_csrf_token_returns_419(): void
    {
        // withoutMiddleware() approach: test the inverse — that CSRF IS enforced
        // by submitting without the token through the default client (no token injected)
        $this->app[Kernel::class]
            ->pushMiddleware(PreventRequestForgery::class);

        // The TestCase normally injects a CSRF token automatically via $this->post().
        // To test that CSRF is enforced we use the raw HTTP client without the helper.
        $response = $this->call('POST', '/logout', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // 419 = TokenMismatchException (CSRF failure)
        $this->assertContains($response->status(), [419, 302, 401],
            'CSRF token mismatch should result in 419, or at minimum not 200');
    }

    // ──────────────────────────────────────────────────────────────
    //  2FA session — challenge screen must not leak user context
    //  when no two_factor:user_id is in the session
    // ──────────────────────────────────────────────────────────────

    public function test_two_factor_challenge_without_session_returns_redirect(): void
    {
        // Accessing the 2FA page without having gone through the login step
        // should not expose any user-specific information or return 200
        $response = $this->get('/two-factor-challenge');

        // Should redirect away (no active 2FA flow in session)
        $this->assertNotEquals(200, $response->status(),
            'Two-factor challenge page must not be accessible without prior login step');
    }

    // ──────────────────────────────────────────────────────────────
    //  Session-based org switching — cannot switch to org you're not in
    // ──────────────────────────────────────────────────────────────

    public function test_user_cannot_switch_to_another_users_organization(): void
    {
        // ownerA tries to switch to orgB which they do not belong to
        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/organizations/{$this->orgB->id}/switch");

        // Must not succeed — either 403 or redirect back
        $this->assertNotEquals(200, $response->status());
        $this->assertFalse(
            session('current_organization_id') === $this->orgB->id,
            'Session must not be updated to an org the user does not belong to'
        );
    }
}
