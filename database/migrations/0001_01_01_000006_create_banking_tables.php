<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('name');
            $table->string('iban')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('currency', 3)->default('CHF');
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->uuid('journal_entry_id')->nullable();
            $table->date('date');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('type'); // credit, debit
            $table->string('reference')->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['bank_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
