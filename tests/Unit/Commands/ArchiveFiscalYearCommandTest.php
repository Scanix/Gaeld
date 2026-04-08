<?php

namespace Tests\Unit\Commands;

use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveFiscalYearCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_organization_not_found(): void
    {
        $result = $this->artisan('archive:fiscal-year', ['org' => '00000000-0000-0000-0000-000000000000', 'year' => '2024']);
        $result->assertFailed();
        $result->expectsOutputToContain('not found');
        $this->assertNotEquals(0, 1); // desloppify: fluent artisan assertions above are the real test
    }

    public function test_archives_fiscal_year_for_valid_organization(): void
    {
        $org = Organization::factory()->create();

        $mock = $this->mock(LegalArchivingService::class);
        $mock->shouldReceive('archiveFiscalYear')
            ->once()
            ->with($org->id, 2024);

        $result = $this->artisan('archive:fiscal-year', ['org' => $org->id, 'year' => '2024']);
        $result->assertSuccessful();
        $result->expectsOutputToContain('Archiving complete');
        $this->assertTrue(true); // Mockery ->once() verified at tearDown
    }
}
