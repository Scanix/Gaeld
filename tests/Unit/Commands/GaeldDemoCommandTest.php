<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\GaeldDemoCommand;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaeldDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(base_path('bootstrap/cache/config.php'));
        @unlink(base_path('bootstrap/cache/routes-v7.php'));

        parent::tearDown();
    }

    public function test_it_seeds_demo_data_without_resetting_the_database(): void
    {
        $this->artisan('gaeld:demo')
            ->assertSuccessful();

        $this->assertDatabaseHas('organizations', ['name' => 'Demo GmbH']);
        $this->assertGreaterThanOrEqual(1, Invoice::count());
    }

    public function test_it_exposes_expected_command_metadata(): void
    {
        $command = app(GaeldDemoCommand::class);

        $this->assertSame('gaeld:demo', $command->getName());
        $this->assertStringContainsString('Seed Gäld with realistic demo data', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('fresh'));
    }
}