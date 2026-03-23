<?php

namespace Tests\Unit\Jobs;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Jobs\GenerateReportsJob;
use App\Domains\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GenerateReportsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_job_prewarms_reports_for_each_organization(): void
    {
        Carbon::setTestNow('2026-03-20 08:00:00');

        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $reportingService = Mockery::mock(ReportingService::class);
        $reportingService->shouldReceive('profitAndLoss')->once()->with($orgA->id, '2026-03-01', '2026-03-20');
        $reportingService->shouldReceive('balanceSheet')->once()->with($orgA->id, '2026-03-20');
        $reportingService->shouldReceive('profitAndLoss')->once()->with($orgB->id, '2026-03-01', '2026-03-20');
        $reportingService->shouldReceive('balanceSheet')->once()->with($orgB->id, '2026-03-20');

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('warning')->never();

        (new GenerateReportsJob())->handle($reportingService);
    }

    public function test_job_continues_after_one_organization_fails(): void
    {
        Carbon::setTestNow('2026-03-20 08:00:00');

        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'EUR']);

        $reportingService = Mockery::mock(ReportingService::class);
        $reportingService->shouldReceive('profitAndLoss')->once()->with($orgA->id, '2026-03-01', '2026-03-20')->andThrow(new \RuntimeException('boom'));
        $reportingService->shouldReceive('balanceSheet')->never()->with($orgA->id, '2026-03-20');
        $reportingService->shouldReceive('profitAndLoss')->once()->with($orgB->id, '2026-03-01', '2026-03-20');
        $reportingService->shouldReceive('balanceSheet')->once()->with($orgB->id, '2026-03-20');

        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use ($orgA) {
            return $message === 'GenerateReportsJob: failed for org'
                && $context['organization_id'] === $orgA->id
                && $context['error'] === 'boom';
        });
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use ($orgB) {
            return $message === 'GenerateReportsJob: pre-warmed reports'
                && $context['organization_id'] === $orgB->id;
        });

        (new GenerateReportsJob())->handle($reportingService);
    }
}