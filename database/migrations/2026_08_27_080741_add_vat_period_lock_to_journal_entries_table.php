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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->date('vat_period_start')->nullable()->after('type');
            $table->date('vat_period_end')->nullable()->after('vat_period_start');
            $table->timestamp('vat_period_locked_at')->nullable()->after('vat_period_end');
            $table->foreignId('vat_period_locked_by_user_id')->nullable()->after('vat_period_locked_at')->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'vat_period_start', 'vat_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['vat_period_locked_by_user_id']);
            $table->dropIndex(['organization_id', 'vat_period_start', 'vat_period_end']);
            $table->dropColumn([
                'vat_period_start',
                'vat_period_end',
                'vat_period_locked_at',
                'vat_period_locked_by_user_id',
            ]);
        });
    }
};
