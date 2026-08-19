<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearPeriodTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYearService $service;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FiscalYearService::class);
        $this->organization = Organization::factory()->create([
            'name' => 'Fiscal Period Test Org',
            'currency' => 'CHF',
        ]);
    }

    public function test_resolves_explicit_period_with_inclusive_boundaries(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => '2024-2025',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Closed,
        ]);

        $period = $this->service->resolvePeriod($this->organization, $fiscalYear->id, 2025);

        $this->assertSame($fiscalYear->id, $period->fiscalYearId);
        $this->assertSame('2024-01-01', $period->fromDate);
        $this->assertSame('2025-06-30', $period->toDate);
        $this->assertTrue($period->containsDate('2024-01-01'));
        $this->assertTrue($period->containsDate('2025-06-30'));
        $this->assertFalse($period->containsDate('2025-07-01'));
        $this->assertFalse($period->isLegacyFallback);
    }

    public function test_explicit_period_takes_precedence_over_year_fallback(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => '2024-2025',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
        ]);

        $period = $this->service->resolvePeriod($this->organization, null, 2024);

        $this->assertSame($fiscalYear->id, $period->fiscalYearId);
        $this->assertSame('2024-01-01', $period->fromDate);
        $this->assertSame('2025-06-30', $period->toDate);
    }

    public function test_legacy_fallback_uses_calendar_year_without_explicit_record(): void
    {
        $period = $this->service->resolvePeriod($this->organization, null, 2025);

        $this->assertNull($period->fiscalYearId);
        $this->assertSame('2025-01-01', $period->fromDate);
        $this->assertSame('2025-12-31', $period->toDate);
        $this->assertTrue($period->isLegacyFallback);
    }

    public function test_explicit_period_must_belong_to_the_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $fiscalYear = FiscalYear::factory()->for($otherOrganization)->create();

        $this->expectException(ModelNotFoundException::class);

        $this->service->resolvePeriod($this->organization, $fiscalYear->id, 2025);
    }
}
