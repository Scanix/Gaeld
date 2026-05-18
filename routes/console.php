<?php

use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Assets\Jobs\MonthlyDepreciationJob;
use App\Domains\Expenses\Jobs\GenerateRecurringExpensesJob;
use App\Domains\Invoicing\Jobs\GenerateRecurringInvoicesJob;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
use App\Domains\Reporting\Jobs\GenerateReportsJob;
use App\Support\FeatureFlag;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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
 * Generate recurring expenses (03:30) — all editions.
 */
Schedule::job(GenerateRecurringExpensesJob::class)->dailyAt('03:30');

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
Schedule::job(MonthlyDepreciationJob::class)->monthlyOn(1, '05:00');

/**
 * Mark expired fiscal years (06:00) — all editions.
 * Transitions operative fiscal years past their end_date to 'expired'
 * and notifies organisation members.
 */
Schedule::call(fn () => app(FiscalYearService::class)->markExpiredAll())->dailyAt('06:00');

// ──────────────────────────────────────────────────────────────
//  Horizon
// ──────────────────────────────────────────────────────────────

// NOTE: Database and file backups are handled by the system-level
// backup scripts in /data/backups/scripts/ (postgres, files) and
// synced nightly to OneDrive via rclone. No app-level backup needed.

/**
 * Horizon queue metrics snapshot (every 5 min).
 */
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// ──────────────────────────────────────────────────────────────
//  Schedule Health Monitoring
// ──────────────────────────────────────────────────────────────

/**
 * Heartbeat check — alerts if the scheduler itself stops running.
 * Configure SCHEDULE_HEARTBEAT_URL in .env (e.g. a Healthchecks.io ping URL).
 */
if ($heartbeatUrl = config('features.schedule_heartbeat_url')) {
    Schedule::call(function () use ($heartbeatUrl) {
        try {
            Http::timeout(5)->connectTimeout(5)->get($heartbeatUrl);
        } catch (Throwable $e) {
            // Heartbeat endpoint is best-effort; never let transient
            // network failures bubble up and pollute error reporting.
        }
    })
        ->everyFiveMinutes()
        ->name('heartbeat');
}
