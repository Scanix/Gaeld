<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_sessions', function (Blueprint $table) {
            $table->string('platform', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('migration_sessions', function (Blueprint $table) {
            $table->string('platform', 20)->change();
        });
    }
};
