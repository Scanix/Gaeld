<?php

use App\Domains\Reporting\Jobs\GenerateReportsJob;
use App\Services\FeatureFlag;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:gaeld', function () {
    $this->comment('Gäld console routes loaded.');
})->purpose('Confirm Gäld console routes are registered.');

// ──────────────────────────────────────────────────────────────
//  Scheduled Jobs
// ──────────────────────────────────────────────────────────────

/**
 * Nightly report cache pre-warming (01:00) — all editions.
 */
Schedule::job(GenerateReportsJob::class)->dailyAt('01:00');

/**
 * Nightly auto-reconciliation (02:00) — EE only.
 * Runs RuleEngineService across all unreconciled transactions.
 */
if (FeatureFlag::enabled('auto_reconciliation')) {
    Schedule::command('gaeld:auto-reconcile')->dailyAt('02:00');
}
