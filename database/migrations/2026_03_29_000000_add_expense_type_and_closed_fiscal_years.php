<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('type', 20)->default('invoice')->after('currency');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->json('closed_fiscal_years')->nullable()->after('fiscal_year_start');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('closed_fiscal_years');
        });
    }
};
