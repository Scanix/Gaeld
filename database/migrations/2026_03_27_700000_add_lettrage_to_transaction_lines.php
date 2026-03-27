<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->string('lettrage_key', 3)->nullable()->after('description')->index();
            $table->string('cost_center_id')->nullable()->after('lettrage_key');
            $table->string('currency', 3)->nullable()->after('cost_center_id');
            $table->string('exchange_rate', 20)->nullable()->after('currency');
            $table->string('amount_local', 20)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropColumn(['lettrage_key', 'cost_center_id', 'currency', 'exchange_rate', 'amount_local']);
        });
    }
};
