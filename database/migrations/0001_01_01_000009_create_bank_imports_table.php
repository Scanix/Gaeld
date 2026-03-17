<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store import metadata for CAMT file imports
        Schema::create('bank_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->string('filename');
            $table->string('format'); // camt053, camt054
            $table->string('statement_id')->nullable();
            $table->integer('transaction_count')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            $table->index(['organization_id', 'created_at']);
        });

        // Add import tracking columns to bank_transactions
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->uuid('bank_import_id')->nullable()->after('bank_account_id');
            $table->uuid('matched_invoice_id')->nullable()->after('is_reconciled');
            $table->uuid('matched_expense_id')->nullable()->after('matched_invoice_id');
            $table->string('debtor_name')->nullable()->after('reference');
            $table->string('creditor_name')->nullable()->after('debtor_name');
            $table->string('end_to_end_id')->nullable()->after('creditor_name');
            $table->string('import_hash')->nullable()->after('end_to_end_id');

            $table->foreign('bank_import_id')->references('id')->on('bank_imports')->nullOnDelete();
            $table->foreign('matched_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('matched_expense_id')->references('id')->on('expenses')->nullOnDelete();
            $table->index('import_hash');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_import_id']);
            $table->dropForeign(['matched_invoice_id']);
            $table->dropForeign(['matched_expense_id']);
            $table->dropColumn([
                'bank_import_id',
                'matched_invoice_id',
                'matched_expense_id',
                'debtor_name',
                'creditor_name',
                'end_to_end_id',
                'import_hash',
            ]);
        });

        Schema::dropIfExists('bank_imports');
    }
};
