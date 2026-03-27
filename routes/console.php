<?php

use App\Domains\Assets\Jobs\MonthlyDepreciationJob;
use App\Domains\Invoicing\Jobs\GenerateRecurringInvoicesJob;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
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
 * Generate recurring invoices (03:00) — all editions.
 */
Schedule::job(GenerateRecurringInvoicesJob::class)->dailyAt('03:00');

/**
 * Send payment reminders for overdue invoices (04:00) — all editions.
 */
Schedule::job(SendPaymentRemindersJob::class)->dailyAt('04:00');

/**
 * Nightly auto-reconciliation (02:00) — EE only.
 * Runs RuleEngineService across all unreconciled transactions.
 */
if (FeatureFlag::enabled('auto_reconciliation')) {
    Schedule::command('gaeld:auto-reconcile')->dailyAt('02:00');
}

/**
 * Monthly depreciation of fixed assets (1st of each month at 05:00).
 */
Schedule::job(MonthlyDepreciationJob::class)->monthly();
