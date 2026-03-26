<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('locale');
            $table->text('invoice_header_text')->nullable()->after('logo_path');
            $table->text('invoice_footer_text')->nullable()->after('invoice_header_text');
            $table->string('invoice_email_subject')->nullable()->after('invoice_footer_text');
            $table->text('invoice_email_body')->nullable()->after('invoice_email_subject');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'invoice_header_text',
                'invoice_footer_text',
                'invoice_email_subject',
                'invoice_email_body',
            ]);
        });
    }
};
