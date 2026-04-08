<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the most frequent query patterns:
     * - Organization-scoped filtered lists (invoices, expenses)
     * - Date-range ledger reports (journal entries)
     * - Balance calculations (transaction lines per account)
     * - Reconciliation views (unreconciled bank transactions)
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'idx_invoices_org_status');
            $table->index(['organization_id', 'issue_date'], 'idx_invoices_org_date');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'idx_expenses_org_status');
            $table->index(['organization_id', 'date'], 'idx_expenses_org_date');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['organization_id', 'date'], 'idx_journal_entries_org_date');
            $table->index(['organization_id', 'is_posted', 'date'], 'idx_journal_entries_org_posted_date');
            $table->index(['organization_id', 'reference'], 'idx_journal_entries_org_ref');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index(['account_id', 'journal_entry_id'], 'idx_txn_lines_account_entry');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'is_reconciled'], 'idx_bank_txn_account_reconciled');
            $table->index(['bank_account_id', 'date'], 'idx_bank_txn_account_date');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index(['organization_id', 'is_active', 'type'], 'idx_accounts_org_active_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_org_status');
            $table->dropIndex('idx_invoices_org_date');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_org_status');
            $table->dropIndex('idx_expenses_org_date');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_journal_entries_org_date');
            $table->dropIndex('idx_journal_entries_org_posted_date');
            $table->dropIndex('idx_journal_entries_org_ref');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('idx_txn_lines_account_entry');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_bank_txn_account_reconciled');
            $table->dropIndex('idx_bank_txn_account_date');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('idx_accounts_org_active_type');
        });
    }
};
