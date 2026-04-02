<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->index('invoice_id');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index('journal_entry_id');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->index('journal_entry_id');
            $table->index('is_reconciled');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex(['journal_entry_id']);
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['journal_entry_id']);
            $table->dropIndex(['is_reconciled']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['reference']);
        });
    }
};
