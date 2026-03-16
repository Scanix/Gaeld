<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('journal_entry_id')->nullable();
            $table->unsignedBigInteger('vat_rate_id')->nullable();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->date('date');
            $table->string('vendor')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('CHF');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('vat_rate_id')->references('id')->on('vat_rates')->nullOnDelete();
            $table->index(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
