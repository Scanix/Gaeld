<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['contacts', 'invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('external_source', 100)->nullable();
                $table->string('external_id', 255)->nullable();
                $table->unique(['organization_id', 'external_source', 'external_id'], $tableName.'_external_reference_unique');
            });
            DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_external_reference_pair CHECK ((external_source IS NULL) = (external_id IS NULL))");
        }
    }

    public function down(): void
    {
        foreach (['contacts', 'invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_external_reference_unique');
                $table->dropColumn(['external_source', 'external_id']);
            });
        }
    }
};
