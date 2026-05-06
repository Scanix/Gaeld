<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final step of customer/supplier unification: drop the role flag columns.
 *
 * Customers and suppliers are now a single Contact record. Whether a contact
 * appears in invoices, expenses, or both is driven entirely by the existence
 * of FK rows in `invoices.customer_id` / `expenses.supplier_id`, not by a flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['is_customer', 'is_supplier']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
        });
    }
};
