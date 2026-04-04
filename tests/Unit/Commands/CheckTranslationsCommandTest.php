<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class CheckTranslationsCommandTest extends TestCase
{
    public function test_runs_without_errors(): void
    {
        $this->artisan('gaeld:check-translations')
            ->assertSuccessful();
    }

    public function test_accepts_specific_language_option(): void
    {
        $this->artisan('gaeld:check-translations', ['--lang' => 'en'])
            ->assertSuccessful();
    }

    public function test_accepts_unused_flag(): void
    {
        $this->artisan('gaeld:check-translations', ['--unused' => true])
            ->assertSuccessful();
    }
}
