<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->foreignUuid('fiscal_year_id')
                ->nullable()
                ->after('fiscal_year')
                ->constrained('fiscal_years')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fiscal_year_id');
        });
    }
};
