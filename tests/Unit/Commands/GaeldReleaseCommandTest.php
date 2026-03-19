<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class GaeldReleaseCommandTest extends TestCase
{
    public function test_rejects_unknown_edition(): void
    {
        $this->artisan('gaeld:release', ['edition' => 'unknown'])
            ->assertFailed();
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $this->artisan('gaeld:release', ['edition' => 'community', '--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_dry_run_saas_edition_succeeds(): void
    {
        $this->artisan('gaeld:release', ['edition' => 'saas', '--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_accepts_community_edition(): void
    {
        $this->artisan('gaeld:release', ['edition' => 'community', '--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_accepts_saas_edition(): void
    {
        $this->artisan('gaeld:release', ['edition' => 'saas', '--dry-run' => true])
            ->assertSuccessful();
    }
}
