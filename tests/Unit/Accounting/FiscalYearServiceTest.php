<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\DTOs\FiscalYearData;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Exceptions\FiscalYearOverlapException;
use App\Domains\Accounting\Exceptions\FiscalYearTooLongException;
use App\Domains\Accounting\Exceptions\InvalidFiscalYearRangeException;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYearService $service;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FiscalYearService::class);
        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Service Test Org',
            'currency' => 'CHF',
        ]);
    }

    public function test_create_standard_fiscal_year(): void
    {
        $fy = $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'FY 2030',
                'start_date' => '2030-01-01',
                'end_date' => '2030-12-31',
            ]),
        );

        $this->assertInstanceOf(FiscalYear::class, $fy);
        $this->assertSame('FY 2030', $fy->name);
        $this->assertSame(FiscalYearStatus::Planned, $fy->status);
    }

    public function test_create_long_fiscal_year_under_23_months(): void
    {
        $fy = $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'Long FY',
                'start_date' => '2030-01-01',
                'end_date' => '2031-11-30', // ~23 months
            ]),
        );

        $this->assertSame(23, $fy->durationInMonths());
    }

    public function test_create_rejects_over_23_months(): void
    {
        $this->expectException(FiscalYearTooLongException::class);

        $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'Too long',
                'start_date' => '2030-01-01',
                'end_date' => '2032-06-30',
            ]),
        );
    }

    public function test_create_rejects_invalid_range(): void
    {
        $this->expectException(InvalidFiscalYearRangeException::class);

        $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'Bad',
                'start_date' => '2030-12-31',
                'end_date' => '2030-01-01',
            ]),
        );
    }

    public function test_create_rejects_overlapping_range(): void
    {
        $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'FY 2030',
                'start_date' => '2030-01-01',
                'end_date' => '2030-12-31',
            ]),
        );

        $this->expectException(FiscalYearOverlapException::class);

        $this->service->create(
            $this->organization,
            FiscalYearData::fromArray([
                'name' => 'Overlap',
                'start_date' => '2030-06-01',
                'end_date' => '2031-05-31',
            ]),
        );
    }

    public function test_close_marks_fiscal_year_closed_and_locks(): void
    {
        $fy = FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $this->service->close($fy, $this->user);

        $fy->refresh();
        $this->assertSame(FiscalYearStatus::Closed, $fy->status);
        $this->assertNotNull($fy->locked_at);
        $this->assertSame($this->user->id, $fy->locked_by_user_id);
    }

    public function test_close_auto_advances_next_planned_year_to_operative(): void
    {
        $current = FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $next = FiscalYear::factory()->for($this->organization)->planned()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->service->close($current, $this->user);

        $next->refresh();
        $this->assertSame(FiscalYearStatus::Operative, $next->status);
    }

    public function test_reopen_returns_closed_year_to_expired(): void
    {
        $fy = FiscalYear::factory()->for($this->organization)->closed()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->service->reopen($fy);

        $fy->refresh();
        $this->assertSame(FiscalYearStatus::Expired, $fy->status);
        $this->assertNull($fy->locked_at);
        $this->assertNull($fy->locked_by_user_id);
    }

    public function test_get_fiscal_year_for_date_within_long_range(): void
    {
        FiscalYear::factory()->for($this->organization)->operative()->create([
            'start_date' => '2025-10-03',
            'end_date' => '2026-12-31',
        ]);

        $found = $this->service->getFiscalYearForDate($this->organization, '2026-08-15');

        $this->assertNotNull($found);
        $this->assertSame('2025-10-03', $found->start_date->toDateString());
    }
}
