<?php

namespace Tests\Feature\Auth;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available_when_organization_exists(): void
    {
        Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $this->get('/login')
            ->assertOk();
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_is_redirected_to_login_when_app_is_initialized(): void
    {
        Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $this->get('/')
            ->assertRedirect(route('login'));
    }
}
