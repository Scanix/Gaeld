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
        Schema::create('edition_runtime_modes', function (Blueprint $table): void {
            $table->string('singleton_key', 32)->primary();
            $table->enum('mode', ['ce', 'ee'])->default('ce');
            $table->enum('migration_status', ['none', 'pending', 'dry_run', 'applied', 'blocked'])
                ->default('none');
            $table->string('contract_version', 32)->default('1.0.0');
            $table->string('ee_version', 32)->nullable();
            $table->json('detected_summary')->nullable();
            $table->json('migration_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_runtime_modes');
    }
};
