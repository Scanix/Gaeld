<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.saas' => false]);
    }

    public function test_setup_wizard_saves_setup_mode_fresh_by_default(): void
    {
        $this->post('/setup', [
            'user_name' => 'Mode Owner',
            'user_email' => 'modeowner@example.com',
            'user_password' => 'password123',
            'user_password_confirmation' => 'password123',
            'org_name' => 'Fresh Org',
            'currency' => 'CHF',
            'locale' => 'en',
        ]);

        $org = Organization::where('name', 'Fresh Org')->firstOrFail();

        $this->assertSame('fresh', $org->setup_mode);
        $this->assertNull($org->founded_at);
    }

    public function test_setup_wizard_saves_migrating_setup_mode(): void
    {
        $this->post('/setup', [
            'user_name' => 'Migrator',
            'user_email' => 'migrator@example.com',
            'user_password' => 'password123',
            'user_password_confirmation' => 'password123',
            'org_name' => 'Migrating Org',
            'currency' => 'CHF',
            'locale' => 'en',
            'setup_mode' => 'migrating',
        ]);

        $org = Organization::where('name', 'Migrating Org')->firstOrFail();

        $this->assertSame('migrating', $org->setup_mode);
    }

    public function test_setup_wizard_saves_founded_at_when_provided(): void
    {
        $this->post('/setup', [
            'user_name' => 'Founder',
            'user_email' => 'founder@example.com',
            'user_password' => 'password123',
            'user_password_confirmation' => 'password123',
            'org_name' => 'Old Org',
            'currency' => 'CHF',
            'locale' => 'en',
            'org_founded_at' => '2015-03-15',
        ]);

        $org = Organization::where('name', 'Old Org')->firstOrFail();

        $this->assertNotNull($org->founded_at);
        $this->assertSame('2015-03-15', $org->founded_at->format('Y-m-d'));
    }

    public function test_setup_wizard_rejects_invalid_setup_mode(): void
    {
        $response = $this->post('/setup', [
            'user_name' => 'Bad Mode',
            'user_email' => 'badmode@example.com',
            'user_password' => 'password123',
            'user_password_confirmation' => 'password123',
            'org_name' => 'Bad Mode Org',
            'currency' => 'CHF',
            'locale' => 'en',
            'setup_mode' => 'unknown_mode',
        ]);

        $response->assertSessionHasErrors('setup_mode');
    }
}
