<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type')->comment('invoice|expense|journal_entry|salary_slip');
            $table->string('document_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('checksum_sha256', 64);
            $table->string('storage_path');
            $table->timestamp('archived_at');
            $table->timestamp('expires_at')->comment('10 years after archived_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'document_type', 'document_id']);
            $table->index(['organization_id', 'fiscal_year']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('updated_at');
        });

        Schema::table('salary_slips', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
        Schema::dropIfExists('legal_archives');
    }
};
