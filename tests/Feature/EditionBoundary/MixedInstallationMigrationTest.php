<?php

namespace Tests\Feature\EditionBoundary;

use App\Support\EditionRuntimeMode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class MixedInstallationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ce_only_dry_run_does_not_persist_runtime_state(): void
    {
        $output = $this->callMigration(['--dry-run' => true]);

        $this->assertStringContainsString('CE runtime detected', $output);

        $this->assertDatabaseCount('edition_runtime_modes', 0);
    }

    public function test_mixed_installation_dry_run_detects_ee_schema_without_modifying_it(): void
    {
        Schema::create('ee_legacy_probe', function ($table): void {
            $table->string('marker');
        });

        try {
            $output = $this->callMigration(['--dry-run' => true]);

            $this->assertStringContainsString('EE schema detected', $output);

            $this->assertTrue(Schema::hasTable('ee_legacy_probe'));
            $this->assertDatabaseCount('edition_runtime_modes', 0);
        } finally {
            Schema::dropIfExists('ee_legacy_probe');
        }
    }

    public function test_ce_runtime_migration_records_explicit_mode_without_deleting_ee_schema(): void
    {
        Schema::create('ee_legacy_probe', function ($table): void {
            $table->string('marker');
        });

        try {
            $this->callMigration([
                '--mode' => EditionRuntimeMode::COMMUNITY_MODE,
                '--force' => true,
            ]);

            $runtimeMode = EditionRuntimeMode::query()->firstOrFail();

            $this->assertSame(EditionRuntimeMode::SINGLETON_KEY, $runtimeMode->singleton_key);
            $this->assertTrue($runtimeMode->isCommunity());
            $this->assertSame(EditionRuntimeMode::MIGRATION_APPLIED, $runtimeMode->migration_status);
            $this->assertTrue(Schema::hasTable('ee_legacy_probe'));
            $this->assertSame('applied', $runtimeMode->migration_status);
        } finally {
            Schema::dropIfExists('ee_legacy_probe');
        }
    }

    /**
     * @param  array<string, bool|string>  $parameters
     */
    private function callMigration(array $parameters): string
    {
        $output = new BufferedOutput;
        $exitCode = $this->app->make(Kernel::class)->call('edition:migrate', $parameters, $output);

        $this->assertSame(0, $exitCode);

        return $output->fetch();
    }
}
