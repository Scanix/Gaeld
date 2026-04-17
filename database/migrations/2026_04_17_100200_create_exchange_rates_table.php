<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('currency_from', 3);
            $table->string('currency_to', 3);
            $table->decimal('rate', 18, 8);
            $table->date('date');
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->unique(['organization_id', 'currency_from', 'currency_to', 'date', 'source'], 'exchange_rates_unique_key');
            $table->index(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
