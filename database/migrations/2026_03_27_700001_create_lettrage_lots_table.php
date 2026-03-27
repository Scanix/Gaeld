<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lettrage_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('letter_key', 3);
            $table->json('line_ids')->comment('Array of transaction_line IDs in this lot');
            $table->foreignId('lettered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lettered_at');
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'account_id', 'letter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lettrage_lots');
    }
};
