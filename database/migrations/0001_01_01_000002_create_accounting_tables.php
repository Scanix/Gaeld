<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('code', 10);
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->date('date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'date']);
        });

        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
