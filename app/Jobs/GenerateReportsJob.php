<?php

namespace App\Jobs;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ReportingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pre-warm report caches for all active organizations.
 *
 * Run nightly (01:00) via the Scheduler.
 * Generates P&L and Balance Sheet for the current month to date,
 * so the first user visit each day hits the cache instead of the DB.
 */
class GenerateReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(ReportingService $reportingService): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            try {
                $fromDate = now()->startOfMonth()->toDateString();
                $toDate = now()->toDateString();

                $reportingService->profitAndLoss($org->id, $fromDate, $toDate);
                $reportingService->balanceSheet($org->id, $toDate);

                Log::info('GenerateReportsJob: pre-warmed reports', [
                    'organization_id' => $org->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('GenerateReportsJob: failed for org', [
                    'organization_id' => $org->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
