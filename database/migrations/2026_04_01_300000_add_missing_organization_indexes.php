<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->index('organization_id');
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->index('organization_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['organization_id', 'status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
        });
    }
};
