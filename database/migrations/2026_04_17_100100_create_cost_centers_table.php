<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
