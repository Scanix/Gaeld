<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('canton', 2);
            $table->string('status', 20)->default('draft');
            $table->json('data')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'fiscal_year', 'canton']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_declarations');
    }
};
