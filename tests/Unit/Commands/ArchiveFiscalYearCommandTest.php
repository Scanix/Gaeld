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
        $this->artisan('archive:fiscal-year', ['org' => '00000000-0000-0000-0000-000000000000', 'year' => '2024'])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_archives_fiscal_year_for_valid_organization(): void
    {
        $org = Organization::factory()->create();

        $mock = $this->mock(LegalArchivingService::class);
        $mock->shouldReceive('archiveFiscalYear')
            ->once()
            ->with($org->id, 2024);

        $this->artisan('archive:fiscal-year', ['org' => $org->id, 'year' => '2024'])
            ->assertSuccessful()
            ->expectsOutputToContain('Archiving complete');
    }
}
