<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
        });

        // Backfill from the parent invoice's organization_id
        DB::statement(<<<'SQL'
            UPDATE invoice_payments
            SET organization_id = invoices.organization_id
            FROM invoices
            WHERE invoice_payments.invoice_id = invoices.id
              AND invoice_payments.organization_id IS NULL
        SQL);

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable(false)->change();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
