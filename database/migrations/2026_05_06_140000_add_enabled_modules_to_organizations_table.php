<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Per-organization overrides for user-toggleable feature modules.
            // Empty/null = inherit global config defaults (backward compatible).
            // Map of module key => bool, e.g. {"budgets": false, "consolidation": true}
            $table->json('enabled_modules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }
};
