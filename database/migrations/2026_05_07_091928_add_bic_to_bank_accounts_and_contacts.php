<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional BIC/SWIFT code to bank accounts and contacts so that
 * pain.001 generation can emit `<BICFI>` inside `DbtrAgt` / `CdtrAgt`.
 *
 * Some Swiss banks (e.g. UBS) enforce a strict XSD profile that rejects
 * `<Othr><Id>NOTPROVIDED</Id></Othr>` and only accepts `<BICFI>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('bic', 11)->nullable()->after('bank_name');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('bic', 11)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bic');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('bic');
        });
    }
};
