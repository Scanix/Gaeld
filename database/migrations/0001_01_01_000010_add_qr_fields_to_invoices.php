<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('qr_reference', 27)->nullable()->after('payment_terms');
            $table->string('qr_type', 10)->nullable()->after('qr_reference'); // QRR, SCOR, NON
            $table->string('qr_iban', 34)->nullable()->after('qr_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['qr_reference', 'qr_type', 'qr_iban']);
        });
    }
};
