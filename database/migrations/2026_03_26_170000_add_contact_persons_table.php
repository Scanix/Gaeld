<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add type column to customers (individual vs organization)
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 20)->default('organization')->after('organization_id');
        });

        // Add type column to suppliers (individual vs organization)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('type', 20)->default('organization')->after('organization_id');
        });

        // Create contact_persons table (polymorphic: belongs to customer or supplier)
        Schema::create('contact_persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('contactable'); // contactable_type + contactable_id (integer to match Customer/Supplier PKs)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable(); // job title / role
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id', 'is_primary'], 'contact_persons_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_persons');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
