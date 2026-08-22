<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('request_key', 255);
            $table->string('http_method', 10);
            $table->string('route', 255);
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'request_key']);
            $table->index(['organization_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
