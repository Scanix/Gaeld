<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type')->default('invoice')->after('status');
            $table->uuid('related_invoice_id')->nullable()->after('type');

            $table->foreign('related_invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['related_invoice_id']);
            $table->dropColumn(['type', 'related_invoice_id']);
        });
    }
};
