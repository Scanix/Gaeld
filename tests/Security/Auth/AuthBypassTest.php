<?php

namespace Tests\Security\Auth;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Security\SecurityTestCase;

/**
 * Verifies that every protected route requires authentication.
 * An unauthenticated request must never return 200.
 */
class AuthBypassTest extends SecurityTestCase
{
    // ──────────────────────────────────────────────────────────────
    //  Web routes — expect redirect to /login (302)
    // ──────────────────────────────────────────────────────────────

    #[DataProvider('protectedWebRoutes')]
    public function test_unauthenticated_web_request_is_redirected(string $method, string $uri): void
    {
        $response = $this->$method($uri);

        $response->assertRedirectContains('/login');
    }

    public static function protectedWebRoutes(): array
    {
        return [
            'dashboard' => ['get', '/'],
            'invoices index' => ['get', '/invoices'],
            'expenses index' => ['get', '/expenses'],
            'contacts index' => ['get', '/contacts'],
            'contacts (suppliers) index' => ['get', '/contacts'],
            'banking index' => ['get', '/banking'],
            'reconciliation' => ['get', '/reconciliation'],
            'search' => ['get', '/search'],
            'organizations list' => ['get', '/organizations'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  REST API routes — expect 401 JSON
    // ──────────────────────────────────────────────────────────────

    #[DataProvider('protectedApiRoutes')]
    public function test_unauthenticated_api_request_returns_401(string $method, string $uri): void
    {
        $response = $this->$method($uri);

        $response->assertUnauthorized();
    }

    public static function protectedApiRoutes(): array
    {
        return [
            'GET invoices' => ['getJson', '/api/v1/invoices'],
            'GET invoices' => ['getJson', '/api/v1/invoices'],
            'GET expenses' => ['getJson', '/api/v1/expenses'],
            'GET accounts' => ['getJson', '/api/v1/accounts'],
            'GET bank-accounts' => ['getJson', '/api/v1/bank-accounts'],
            'GET webhooks' => ['getJson', '/api/v1/webhooks'],
            'GET tokens' => ['getJson', '/api/v1/tokens'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Guest-only routes — redirect away when already authenticated
    // ──────────────────────────────────────────────────────────────

    public function test_authenticated_user_cannot_access_login_page(): void
    {
        $this->actingAs($this->ownerA)
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_authenticated_user_cannot_access_register_page(): void
    {
        $this->actingAs($this->ownerA)
            ->get('/register')
            ->assertRedirect('/');
    }

    // ──────────────────────────────────────────────────────────────
    //  Email verification gate
    // ──────────────────────────────────────────────────────────────

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->orgA->users()->attach($unverified->id, ['role' => 'member']);
        $this->assignOrganizationRole($unverified, $this->orgA, 'member');

        $this->actingAs($unverified)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/')
            ->assertRedirectContains('/email/verify');
    }

    // ──────────────────────────────────────────────────────────────
    //  SaaS admin endpoint — must require auth
    // ──────────────────────────────────────────────────────────────

    public function test_saas_admin_requires_authentication(): void
    {
        if (! Route::has('saas-admin.index')) {
            $this->markTestSkipped('EE plugin not loaded');
        }

        $this->get('/saas-admin')
            ->assertRedirectContains('/login');
    }
}
