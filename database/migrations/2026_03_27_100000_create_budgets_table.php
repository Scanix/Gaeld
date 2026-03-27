<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('monthly_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'account_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
