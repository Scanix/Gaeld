<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class UserProfileHttpTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->setUpOrganization(['password' => Hash::make('password')]);
    }

    public function test_profile_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Users/Profile'));
    }

    public function test_profile_update_changes_name_and_locale(): void
    {
        $this->actingAs($this->user)
            ->put('/profile', [
                'name' => 'Updated Name',
                'locale' => 'fr',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'locale' => 'fr',
        ]);
    }

    public function test_profile_update_validates_locale(): void
    {
        $this->actingAs($this->user)
            ->put('/profile', [
                'name' => 'Test',
                'locale' => 'invalid',
            ])
            ->assertSessionHasErrors('locale');
    }

    public function test_password_update_requires_current_password(): void
    {
        $this->actingAs($this->user)
            ->put('/profile/password', [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_update_changes_password(): void
    {
        $newPassword = 'G@eld!Tr5zk9Qm#2026';

        $response = $this->actingAs($this->user)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->user->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->user->password));
    }

    public function test_toggle_help_changes_preference(): void
    {
        $initial = $this->user->show_help;

        $this->actingAs($this->user)
            ->post('/profile/toggle-help')
            ->assertRedirect();

        $this->user->refresh();
        $this->assertNotEquals($initial, $this->user->show_help);
    }

    public function test_email_change_requires_current_password(): void
    {
        $this->actingAs($this->user)
            ->put('/profile/email', [
                'email' => 'new@example.ch',
                'current_password' => 'wrong',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_email_change_sets_pending_email(): void
    {
        $this->actingAs($this->user)
            ->put('/profile/email', [
                'email' => 'newemail@example.ch',
                'current_password' => 'password',
            ])
            ->assertRedirect();

        $this->user->refresh();
        $this->assertEquals('newemail@example.ch', $this->user->pending_email);
        $this->assertNotNull($this->user->email_change_token);
    }

    public function test_confirm_email_change_with_invalid_token_flashes_error(): void
    {
        $this->user->update([
            'pending_email' => 'newemail@example.ch',
            'email_change_token' => hash('sha256', 'valid-token'),
            'email_change_requested_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(5), [
            'token' => 'wrong-token',
        ]);

        $response = $this->actingAs($this->user)->get($signedUrl);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error', __('app.email_change_invalid'));
    }

    public function test_confirm_email_change_with_expired_token_flashes_error_and_clears_pending_state(): void
    {
        $token = 'valid-token';
        $this->user->update([
            'pending_email' => 'newemail@example.ch',
            'email_change_token' => hash('sha256', $token),
            'email_change_requested_at' => now()->subHours(25),
        ]);

        $signedUrl = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(5), [
            'token' => $token,
        ]);

        $response = $this->actingAs($this->user)->get($signedUrl);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error', __('app.email_change_expired'));

        $this->user->refresh();
        $this->assertNull($this->user->pending_email);
        $this->assertNull($this->user->email_change_token);
        $this->assertNull($this->user->email_change_requested_at);
    }

    public function test_cancel_email_change_clears_pending(): void
    {
        $this->user->update([
            'pending_email' => 'pending@example.ch',
            'email_change_token' => 'some-token',
        ]);

        $this->actingAs($this->user)
            ->delete('/profile/email')
            ->assertRedirect();

        $this->user->refresh();
        $this->assertNull($this->user->pending_email);
        $this->assertNull($this->user->email_change_token);
    }

    public function test_account_deletion_requires_password(): void
    {
        $this->actingAs($this->user)
            ->delete('/profile', [
                'current_password' => 'wrong',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_export_data_queues_job(): void
    {
        $this->actingAs($this->user)
            ->post('/profile/export')
            ->assertRedirect();
    }
}
