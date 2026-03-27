<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('period_date');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
    }
};
