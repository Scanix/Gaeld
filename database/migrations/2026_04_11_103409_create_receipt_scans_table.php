<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scan_id')->unique();
            $table->string('receipt_path');
            $table->string('status')->default('pending'); // pending | completed | failed | validated
            $table->jsonb('extracted_data')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['organization_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_scans');
    }
};
