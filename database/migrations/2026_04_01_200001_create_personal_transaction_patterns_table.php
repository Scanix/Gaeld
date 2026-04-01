<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_transaction_patterns', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('counterparty_name');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'counterparty_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_transaction_patterns');
    }
};
