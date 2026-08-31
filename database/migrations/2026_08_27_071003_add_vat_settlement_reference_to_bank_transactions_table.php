<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignUuid('vat_settlement_journal_entry_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->unique('vat_settlement_journal_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropUnique(['vat_settlement_journal_entry_id']);
            $table->dropForeign(['vat_settlement_journal_entry_id']);
            $table->dropColumn('vat_settlement_journal_entry_id');
        });
    }
};
