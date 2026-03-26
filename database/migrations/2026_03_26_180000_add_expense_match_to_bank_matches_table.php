<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add expense match support to bank_matches table.
     *
     * The bank_matches table previously only handled invoice suggestions.
     * This migration makes it the unified match/suggestion store by:
     * - Making invoice_id nullable (match may be invoice OR expense)
     * - Adding expense_id (nullable) for expense match suggestions
     * - Adding suggested_expense_category for rule-engine category suggestions
     * - Dropping the unique constraint that prevented expense-only rows
     *
     * The confirmed match FKs (matched_invoice_id, matched_expense_id) on
     * bank_transactions remain — they represent the authoritative reconciled state.
     */
    public function up(): void
    {
        Schema::table('bank_matches', function (Blueprint $table) {
            // Drop unique constraint before modifying column (PostgreSQL requires this)
            $table->dropUnique(['bank_transaction_id', 'invoice_id']);

            // Make invoice_id nullable — a match may target an expense instead
            $table->uuid('invoice_id')->nullable()->change();

            // Add expense match columns
            $table->uuid('expense_id')->nullable()->after('invoice_id');
            $table->string('suggested_expense_category')->nullable()->after('expense_id');

            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();

            // New unique constraint: one match record per (transaction, invoice) or (transaction, expense)
            $table->unique(['bank_transaction_id', 'invoice_id'], 'bank_matches_transaction_invoice_unique');
            $table->unique(['bank_transaction_id', 'expense_id'], 'bank_matches_transaction_expense_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_matches', function (Blueprint $table) {
            $table->dropUnique('bank_matches_transaction_invoice_unique');
            $table->dropUnique('bank_matches_transaction_expense_unique');
            $table->dropForeign(['expense_id']);
            $table->dropColumn(['expense_id', 'suggested_expense_category']);
            $table->uuid('invoice_id')->nullable(false)->change();
            $table->unique(['bank_transaction_id', 'invoice_id']);
        });
    }
};
