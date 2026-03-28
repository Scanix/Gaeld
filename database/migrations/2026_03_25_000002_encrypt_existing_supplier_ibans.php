<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Encrypt existing plaintext IBANs in-place.
        // The Supplier model now has 'iban' => 'encrypted' cast, so we
        // must convert existing plaintext values before the cast takes effect.
        DB::table('suppliers')
            ->whereNotNull('iban')
            ->where('iban', '!=', '')
            ->orderBy('id')
            ->each(function (object $supplier) {
                // Skip values that are already encrypted (idempotent).
                $alreadyEncrypted = false;
                try {
                    Crypt::decryptString($supplier->iban);
                    $alreadyEncrypted = true;
                } catch (DecryptException) {
                    $alreadyEncrypted = false;
                }

                if ($alreadyEncrypted) {
                    return;
                }

                DB::table('suppliers')
                    ->where('id', $supplier->id)
                    ->update(['iban' => Crypt::encryptString($supplier->iban)]);
            });
    }

    public function down(): void
    {
        // Decrypt all IBANs back to plaintext.
        DB::table('suppliers')
            ->whereNotNull('iban')
            ->where('iban', '!=', '')
            ->orderBy('id')
            ->each(function (object $supplier) {
                try {
                    $plain = Crypt::decryptString($supplier->iban);
                } catch (DecryptException) {
                    return; // Already plaintext — skip.
                }

                DB::table('suppliers')
                    ->where('id', $supplier->id)
                    ->update(['iban' => $plain]);
            });
    }
};
