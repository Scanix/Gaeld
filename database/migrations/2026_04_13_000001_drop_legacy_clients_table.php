<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });

        Schema::dropIfExists('clients');
    }

    public function down(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 2)->default('CH');
            $table->string('vat_number')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'name']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('organization_id');
        });
    }
};
