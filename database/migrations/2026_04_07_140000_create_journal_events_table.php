<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->jsonb('payload');
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->index(['organization_id', 'occurred_at']);
            $table->index(['journal_entry_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_events');
    }
};
