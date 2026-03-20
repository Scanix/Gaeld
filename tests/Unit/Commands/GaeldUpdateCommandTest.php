<?php

namespace Tests\Unit\Commands;

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
}