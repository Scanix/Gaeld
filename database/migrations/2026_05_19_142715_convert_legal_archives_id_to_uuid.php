<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop integer primary key and replace with UUID.
        // Uses two separate Schema::table calls because some DBMS drivers
        // do not allow dropping and adding columns of the same name in one pass.
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('legal_archives', function (Blueprint $table) {
            // gen_random_uuid() fills any existing rows; new rows get UUIDs
            // from the Eloquent HasUuids trait before the INSERT.
            $table->uuid('id')
                ->default(DB::raw('gen_random_uuid()'))
                ->primary();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_archives', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropColumn('id');
        });

        Schema::table('legal_archives', function (Blueprint $table) {
            $table->id();
        });
    }
};
