<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class CheckTranslationsCommandTest extends TestCase
{
    public function test_runs_without_errors(): void
    {
        $result = $this->artisan('gaeld:check-translations');
        $result->assertSuccessful();
        $this->assertTrue(true);
    }

    public function test_accepts_specific_language_option(): void
    {
        $result = $this->artisan('gaeld:check-translations', ['--lang' => 'en']);
        $result->assertSuccessful();
        $this->assertTrue(true);
    }

    public function test_accepts_unused_flag(): void
    {
        $result = $this->artisan('gaeld:check-translations', ['--unused' => true]);
        $result->assertSuccessful();
        $this->assertTrue(true);
    }
}
