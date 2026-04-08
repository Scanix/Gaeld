<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `uuid` column to models that still use integer auto-increment PKs
 * but are exposed in URLs. Keeps `id` as internal FK for performance.
 */
return new class extends Migration
{
    private array $tables = [
        'accounts',
        'bank_accounts',
        'customers',
        'suppliers',
        'recurring_invoices',
        'depreciation_entries',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Backfill existing rows with generated UUIDs
        foreach ($this->tables as $table) {
            DB::statement("UPDATE {$table} SET uuid = gen_random_uuid() WHERE uuid IS NULL");
        }

        // Make non-nullable and add unique index
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable(false)->unique()->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
