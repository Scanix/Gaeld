<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_sessions', function (Blueprint $table) {
            $table->json('imported_record_ids')->default('{}')->after('imported_counts');
        });
    }

    public function down(): void
    {
        Schema::table('migration_sessions', function (Blueprint $table) {
            $table->dropColumn('imported_record_ids');
        });
    }
};
