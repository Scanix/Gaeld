<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_reference', 120)->nullable();
            $table->string('purchase_order_reference', 120)->nullable();
            $table->string('delivery_reference', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_reference', 'purchase_order_reference', 'delivery_reference']);
        });
    }
};
