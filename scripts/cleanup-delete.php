#!/usr/bin/env php
<?php

/**
 * Production data cleanup: DELETE data before 2026-01-01
 *
 * Deletes in safe order (payments → invoices → journal entries → expenses).
 * Wrapped in a transaction — rolls back on any error.
 *
 * ALWAYS run cleanup-preview.php first and confirm the counts!
 *
 * Usage (on production server):
 *   php scripts/cleanup-delete.php
 *
 * To restrict to one organization:
 *   ORG_ID=<uuid> php scripts/cleanup-delete.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$cutoff = '2026-01-01';
$targetOrgId = getenv('ORG_ID') ?: null;

echo "=== DATA CLEANUP — deleting all data before {$cutoff} ===\n";
if ($targetOrgId) {
    echo "Restricted to org: {$targetOrgId}\n";
}
echo "\n";
echo "Type 'yes' and press Enter to proceed, or anything else to abort: ";
$handle = fopen('php://stdin', 'r');
$confirm = trim(fgets($handle));
fclose($handle);

if ($confirm !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

DB::transaction(function () use ($cutoff, $targetOrgId) {
    // ── 1. Invoice payments ───────────────────────────────────────────────
    $paymentQuery = DB::table('invoice_payments as p')
        ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
        ->where('p.payment_date', '<', $cutoff);

    if ($targetOrgId) {
        $paymentQuery->where('i.organization_id', $targetOrgId);
    }

    // Get the IDs first (join-based delete not supported on all DBs)
    $paymentIds = $paymentQuery->pluck('p.id');
    $deletedPayments = DB::table('invoice_payments')->whereIn('id', $paymentIds)->delete();
    echo "Deleted {$deletedPayments} invoice payments\n";

    // ── 2. Invoices ───────────────────────────────────────────────────────
    $invoiceQuery = DB::table('invoices')
        ->where('issue_date', '<', $cutoff);

    if ($targetOrgId) {
        $invoiceQuery->where('organization_id', $targetOrgId);
    }

    $deletedInvoices = $invoiceQuery->delete();
    echo "Deleted {$deletedInvoices} invoices\n";

    // ── 3. Journal entries (lines cascade via FK) ─────────────────────────
    // journal_entry_lines have cascadeOnDelete from journal_entries
    $journalQuery = DB::table('journal_entries')
        ->where('date', '<', $cutoff);

    if ($targetOrgId) {
        $journalQuery->where('organization_id', $targetOrgId);
    }

    $deletedJournalEntries = $journalQuery->delete();
    echo "Deleted {$deletedJournalEntries} journal entries (lines cascade)\n";

    // ── 4. Expenses ───────────────────────────────────────────────────────
    $expenseQuery = DB::table('expenses')
        ->where('date', '<', $cutoff);

    if ($targetOrgId) {
        $expenseQuery->where('organization_id', $targetOrgId);
    }

    $deletedExpenses = $expenseQuery->delete();
    echo "Deleted {$deletedExpenses} expenses\n";

    echo "\nAll deletions committed successfully.\n";
});
