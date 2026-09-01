<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('document_id');
            $table->dropUnique(['organization_id', 'document_type', 'document_id']);
            $table->unique(['organization_id', 'document_type', 'document_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'document_type', 'document_id', 'version']);
            $table->dropColumn('version');
            $table->unique(['organization_id', 'document_type', 'document_id']);
        });
    }
};
