<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->string('code', 20);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('vat_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('journal_entry_id');
            $table->unsignedBigInteger('vat_rate_id');
            $table->decimal('base_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->string('type'); // input, output
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('vat_rate_id')->references('id')->on('vat_rates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_entries');
        Schema::dropIfExists('vat_rates');
    }
};
