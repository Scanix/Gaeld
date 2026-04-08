<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `uuid` column to personal_access_tokens and vat_rates so their
 * integer PKs are never exposed in the API (enumeration attack prevention).
 */
return new class extends Migration
{
    private array $tables = [
        'personal_access_tokens',
        'vat_rates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        foreach ($this->tables as $table) {
            DB::statement("UPDATE {$table} SET uuid = gen_random_uuid() WHERE uuid IS NULL");
        }

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
