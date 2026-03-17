<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add structured_reference to bank_transactions
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('structured_reference')->nullable()->after('end_to_end_id');
        });

        // Add unique index on qr_reference per organization for invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['organization_id', 'qr_reference'], 'invoices_org_qr_reference_unique');
        });

        // Create bank_matches table for storing match candidates with confidence
        Schema::create('bank_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_transaction_id');
            $table->uuid('invoice_id');
            $table->integer('confidence'); // 100 = exact QR, 90 = amount+client, 70 = heuristic
            $table->string('match_type'); // qr_reference, amount_client, heuristic
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('bank_transaction_id')->references('id')->on('bank_transactions')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->unique(['bank_transaction_id', 'invoice_id']);
            $table->index(['bank_transaction_id', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_matches');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_org_qr_reference_unique');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('structured_reference');
        });
    }
};
