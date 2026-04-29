<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class TwoFactorHttpTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->setUpOrganization(['password' => Hash::make('password')]);
    }

    public function test_two_factor_confirm_without_setup_flashes_error(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/profile')
            ->post('/profile/two-factor/confirm', [
                'code' => '123456',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error', __('app.two_factor_not_started'));
    }

    public function test_two_factor_recovery_codes_without_enabled_two_factor_flashes_error(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/profile')
            ->post('/profile/two-factor/recovery-codes', [
                'current_password' => 'password',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error', __('app.two_factor_not_enabled'));
    }
}
