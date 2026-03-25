<?php

use App\Domains\Reporting\Jobs\GenerateReportsJob;
use App\Support\FeatureFlag;
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
 * Mark overdue invoices (02:00) — all editions.
 */
Schedule::command('invoices:mark-overdue')->dailyAt('02:00');

/**
 * Nightly auto-reconciliation (02:00) — EE only.
 * Runs RuleEngineService across all unreconciled transactions.
 */
if (FeatureFlag::enabled('auto_reconciliation')) {
    Schedule::command('gaeld:auto-reconcile')->dailyAt('02:00');
}
