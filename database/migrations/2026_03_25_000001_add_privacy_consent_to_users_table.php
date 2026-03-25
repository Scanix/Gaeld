<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('accepted_privacy_at')->nullable()->after('show_help');
            $table->timestamp('accepted_terms_at')->nullable()->after('accepted_privacy_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accepted_privacy_at', 'accepted_terms_at']);
        });
    }
};
