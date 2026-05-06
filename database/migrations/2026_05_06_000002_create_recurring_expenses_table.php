<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('organization_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('vat_rate_id')->nullable();
            $table->string('vendor')->nullable();
            $table->string('currency', 3)->default('CHF');
            $table->string('payment_method')->nullable();
            $table->string('expense_account_code')->nullable();
            $table->string('bank_account_code')->nullable();
            $table->string('frequency'); // monthly, quarterly, yearly
            $table->date('next_due_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('vat_rate_id')->references('id')->on('vat_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
