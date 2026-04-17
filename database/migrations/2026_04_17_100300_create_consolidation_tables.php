<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('member_organization_ids');
            $table->string('base_currency', 3)->default('CHF');
            $table->timestamps();
        });

        Schema::create('consolidation_eliminations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consolidation_group_id')->constrained('consolidation_groups')->cascadeOnDelete();
            $table->foreignId('account_debit_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('account_credit_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['consolidation_group_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidation_eliminations');
        Schema::dropIfExists('consolidation_groups');
    }
};
