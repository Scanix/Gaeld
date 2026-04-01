<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_mixed_use')->default(false)->after('is_active');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->boolean('is_personal')->nullable()->after('is_reconciled');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('is_mixed_use');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });
    }
};
