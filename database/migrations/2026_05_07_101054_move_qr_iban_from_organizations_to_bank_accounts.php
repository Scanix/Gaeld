<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns to bank_accounts.
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('qr_iban', 34)->nullable()->after('iban');
            $table->boolean('is_default_for_invoicing')->default(false)->after('is_mixed_use');
        });

        // 2. Backfill: for each org with a qr_iban, find or create the matching
        //    bank account and copy the QR-IBAN onto it. Mark it as the default
        //    invoicing account for the org.
        $orgs = DB::table('organizations')
            ->whereNotNull('qr_iban')
            ->where('qr_iban', '!=', '')
            ->get(['id', 'name', 'qr_iban', 'currency']);

        foreach ($orgs as $org) {
            $qrIban = strtoupper(preg_replace('/\s+/', '', $org->qr_iban) ?? '');
            if ($qrIban === '') {
                continue;
            }

            // Try to match an existing bank account on this org by IBAN/QR-IBAN.
            $existing = DB::table('bank_accounts')
                ->where('organization_id', $org->id)
                ->whereNull('deleted_at')
                ->get(['id', 'iban'])
                ->first(fn ($ba) => $ba->iban !== null
                    && strtoupper(preg_replace('/\s+/', '', $ba->iban) ?? '') === $qrIban);

            if ($existing !== null) {
                DB::table('bank_accounts')->where('id', $existing->id)->update([
                    'qr_iban' => $qrIban,
                    'is_default_for_invoicing' => true,
                    'updated_at' => now(),
                ]);

                continue;
            }

            // Otherwise create a new bank account dedicated to QR-bills.
            DB::table('bank_accounts')->insert([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $org->id,
                'name' => 'QR-Bill account',
                'qr_iban' => $qrIban,
                'currency' => $org->currency ?: 'CHF',
                'balance' => '0.00',
                'is_active' => true,
                'is_mixed_use' => false,
                'is_default_for_invoicing' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop the column from organizations.
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('qr_iban');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('qr_iban', 34)->nullable()->after('vat_number');
        });

        // Best-effort restore: copy qr_iban from the default invoicing bank account.
        $accounts = DB::table('bank_accounts')
            ->where('is_default_for_invoicing', true)
            ->whereNotNull('qr_iban')
            ->get(['organization_id', 'qr_iban']);

        foreach ($accounts as $ba) {
            DB::table('organizations')
                ->where('id', $ba->organization_id)
                ->update(['qr_iban' => $ba->qr_iban]);
        }

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['qr_iban', 'is_default_for_invoicing']);
        });
    }
};
