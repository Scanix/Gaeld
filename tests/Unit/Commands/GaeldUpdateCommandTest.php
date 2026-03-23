<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\GaeldUpdateCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaeldUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(base_path('bootstrap/cache/config.php'));
        @unlink(base_path('bootstrap/cache/routes-v7.php'));

        parent::tearDown();
    }

    public function test_it_succeeds_when_migrations_and_cache_are_skipped(): void
    {
        $this->artisan('gaeld:update', [
            '--skip-migrations' => true,
            '--skip-cache' => true,
        ])->assertSuccessful();
    }

    public function test_it_exposes_expected_command_metadata(): void
    {
        $command = new GaeldUpdateCommand();

        $this->assertSame('gaeld:update', $command->getName());
        $this->assertStringContainsString('Update Gäld', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('skip-migrations'));
        $this->assertTrue($command->getDefinition()->hasOption('skip-cache'));
    }
}