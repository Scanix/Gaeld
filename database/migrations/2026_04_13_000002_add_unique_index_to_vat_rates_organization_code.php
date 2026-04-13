<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'code']);
        });
    }
};
