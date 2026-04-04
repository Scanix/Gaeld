<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_successfully_with_no_users(): void
    {
        $result = $this->artisan('gaeld:sync-permissions');
        $result->assertSuccessful();
        $result->expectsOutputToContain('Done');
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_debug_mode_runs_successfully(): void
    {
        $result = $this->artisan('gaeld:sync-permissions', ['--debug' => true]);
        $result->assertSuccessful();
        $this->assertTrue(true); // verifies debug mode doesn't throw
    }
}
