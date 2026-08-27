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
        Schema::table('tax_declarations', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('finalized_at');
            $table->foreignId('locked_by_user_id')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_declarations', function (Blueprint $table) {
            $table->dropForeign(['locked_by_user_id']);
            $table->dropColumn(['locked_at', 'locked_by_user_id']);
        });
    }
};
