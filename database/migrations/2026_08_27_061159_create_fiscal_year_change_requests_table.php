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
        Schema::create('fiscal_year_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('current_start', 5);
            $table->string('requested_start', 5);
            $table->unsignedSmallInteger('effective_year');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX fiscal_year_change_requests_one_pending_per_org '
            .'ON fiscal_year_change_requests (organization_id) WHERE status = \'pending\'',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fiscal_year_change_requests_one_pending_per_org');
        Schema::dropIfExists('fiscal_year_change_requests');
    }
};
