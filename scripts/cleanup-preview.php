#!/usr/bin/env php
<?php

/**
 * Production data cleanup: PREVIEW ONLY
 *
 * Shows counts of data with date < 2026-01-01 without deleting anything.
 * Run on the production server BEFORE running cleanup-delete.php.
 *
 * Usage:
 *   php scripts/cleanup-preview.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$cutoff = '2026-01-01';

echo "=== DATA CLEANUP PREVIEW — all data before {$cutoff} ===\n\n";

// ── Organizations ─────────────────────────────────────────────────────────────
$orgs = DB::table('organizations')->select('id', 'name')->get();
echo "Organizations in DB:\n";
foreach ($orgs as $org) {
    echo "  [{$org->id}] {$org->name}\n";
}
echo "\n";

// ── Counts per organization ───────────────────────────────────────────────────
foreach ($orgs as $org) {
    echo "─── Org: {$org->name} ({$org->id}) ───\n";

    $journalEntries = DB::table('journal_entries')
        ->where('organization_id', $org->id)
        ->where('date', '<', $cutoff)
        ->count();

    $invoices = DB::table('invoices')
        ->where('organization_id', $org->id)
        ->where('issue_date', '<', $cutoff)
        ->count();

    // Payments linked to invoices in this org that are before cutoff
    $payments = DB::table('invoice_payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->where('i.organization_id', $org->id)
        ->where('p.payment_date', '<', $cutoff)
        ->count();

    $expenses = DB::table('expenses')
        ->where('organization_id', $org->id)
        ->where('date', '<', $cutoff)
        ->count();

    echo "  Journal entries : {$journalEntries}\n";
    echo "  Invoices        : {$invoices}\n";
    echo "  Payments        : {$payments}\n";
    echo "  Expenses        : {$expenses}\n";
    echo "\n";
}

echo "=== END PREVIEW ===\n";
echo "To delete, run: php scripts/cleanup-delete.php\n";
