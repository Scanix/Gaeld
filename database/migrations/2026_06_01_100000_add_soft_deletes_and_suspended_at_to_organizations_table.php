<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add deleted_at (soft delete) and suspended_at to organizations so SaaS
 * operators can soft-delete or temporarily lock out tenants without
 * touching their data. Both are nullable timestamps; a non-null value
 * means the org is in that state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->softDeletes();
            $table->timestamp('suspended_at')->nullable()->after('deleted_at');
            $table->string('suspended_reason', 500)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['suspended_reason', 'suspended_at']);
            $table->dropSoftDeletes();
        });
    }
};
