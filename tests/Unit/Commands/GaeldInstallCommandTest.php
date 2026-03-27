<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\GaeldInstallCommand;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaeldInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(base_path('bootstrap/cache/config.php'));
        @unlink(base_path('bootstrap/cache/routes-v7.php'));

        parent::tearDown();
    }

    public function test_it_installs_default_admin_and_organization_in_no_interaction_mode(): void
    {
        $this->artisan('gaeld:install', ['--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_it_returns_success_without_creating_a_second_installation(): void
    {
        Organization::create([
            'name' => 'Existing Org',
            'legal_name' => 'Existing Org AG',
            'currency' => 'CHF',
            'locale' => 'en',
            'country' => 'CH',
        ]);

        $this->artisan('gaeld:install', ['--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_it_exposes_expected_command_metadata(): void
    {
        $command = app(GaeldInstallCommand::class);

        $this->assertSame('gaeld:install', $command->getName());
        $this->assertStringContainsString('Install Gäld', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('demo'));
    }
}
