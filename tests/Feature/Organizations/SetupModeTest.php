<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\ChecklistService;
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

    public function test_checklist_hides_data_import_for_fresh_orgs(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'fresh',
            'enabled_modules' => [],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertNotContains('checklist_data_imported', $keys);
    }

    public function test_checklist_shows_data_import_for_migrating_orgs(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'migrating',
            'enabled_modules' => [],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertContains('checklist_data_imported', $keys);
    }

    public function test_checklist_hides_assets_item_when_module_disabled(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'fresh',
            'enabled_modules' => ['assets' => false],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertNotContains('checklist_depreciation_posted', $keys);
    }

    public function test_checklist_shows_assets_item_when_module_enabled(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'fresh',
            'enabled_modules' => ['assets' => true],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertContains('checklist_depreciation_posted', $keys);
    }

    public function test_checklist_hides_fiduciary_export_item_when_module_disabled(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'fresh',
            'enabled_modules' => ['fiduciary_export' => false],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertNotContains('checklist_fiduciary_exported', $keys);
    }

    public function test_checklist_shows_fiduciary_export_item_when_module_enabled(): void
    {
        /** @var Organization $org */
        $org = Organization::factory()->create([
            'setup_mode' => 'fresh',
            'enabled_modules' => ['fiduciary_export' => true],
        ]);

        $service = app(ChecklistService::class);
        $checklist = $service->checklist($org->id);

        $keys = array_column($checklist['accounting'], 'key');
        $this->assertContains('checklist_fiduciary_exported', $keys);
    }
}
