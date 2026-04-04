<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_successfully_with_no_users(): void
    {
        $this->artisan('gaeld:sync-permissions')
            ->assertSuccessful()
            ->expectsOutputToContain('Done');
    }

    public function test_debug_mode_runs_successfully(): void
    {
        $this->artisan('gaeld:sync-permissions', ['--debug' => true])
            ->assertSuccessful();
    }
}
