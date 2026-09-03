<?php

namespace Tests\Feature\EditionBoundary;

use App\Support\EditionRuntimeMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EditionRuntimeModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_runtime_mode_is_an_installation_level_singleton_with_ce_defaults(): void
    {
        $this->assertTrue(Schema::hasTable('edition_runtime_modes'));

        $runtimeMode = EditionRuntimeMode::query()->create();

        $this->assertSame(EditionRuntimeMode::SINGLETON_KEY, $runtimeMode->singleton_key);
        $this->assertSame(EditionRuntimeMode::COMMUNITY_MODE, $runtimeMode->mode);
        $this->assertSame(EditionRuntimeMode::NO_MIGRATION, $runtimeMode->migration_status);
        $this->assertSame('1.0.0', $runtimeMode->contract_version);
        $this->assertNull($runtimeMode->ee_version);
        $this->assertTrue($runtimeMode->isCommunity());
        $this->assertFalse($runtimeMode->isCommercial());
    }

    public function test_runtime_mode_uses_a_fixed_singleton_key_without_organization_scope(): void
    {
        $columns = Schema::getColumnListing('edition_runtime_modes');

        $this->assertContains('singleton_key', $columns);
        $this->assertNotContains('organization_id', $columns);
        $this->assertNotContains('id', $columns);
    }
}
