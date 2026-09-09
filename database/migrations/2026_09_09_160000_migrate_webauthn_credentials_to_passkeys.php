<?php

use Cbor\Encoder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Support\WebAuthn;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('webauthn_credentials')) {
            return;
        }

        DB::table('webauthn_credentials')
            ->orderBy('created_at')
            ->each(function (object $credential): void {
                $credentialData = self::credentialRecord($credential);

                $credentialId = (string) $credential->id;
                if (DB::table('passkeys')->where('credential_id', $credentialId)->exists()) {
                    return;
                }

                DB::table('passkeys')->insert([
                    'user_id' => $credential->authenticatable_id,
                    'name' => $credential->alias ?: 'Passkey',
                    'credential_id' => $credentialId,
                    'credential' => WebAuthn::toJson($credentialData),
                    'last_used_at' => null,
                    'created_at' => $credential->created_at,
                    'updated_at' => $credential->updated_at,
                ]);
            });
    }

    private static function credentialRecord(object $credential): CredentialRecord
    {
        $publicKey = Crypt::decryptString((string) $credential->public_key);
        $keyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));

        if (! is_array($keyDetails['ec'] ?? null)) {
            throw new RuntimeException('Unable to convert a legacy WebAuthn public key.');
        }

        $ec = $keyDetails['ec'];
        $x = $ec['x'] ?? null;
        $y = $ec['y'] ?? null;
        if (! is_string($x) || ! is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
            throw new RuntimeException('Legacy WebAuthn key is not a P-256 credential.');
        }

        $credentialPublicKey = (new Encoder)->encode([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => $x,
            -3 => $y,
        ]);
        $aaguid = is_string($credential->aaguid) && Uuid::isValid($credential->aaguid)
            ? Uuid::fromString($credential->aaguid)
            : Uuid::fromString('00000000-0000-0000-0000-000000000000');
        $transports = json_decode((string) $credential->transports, true);

        return CredentialRecord::create(
            (string) $credential->id,
            'public-key',
            is_array($transports) ? array_values($transports) : [],
            (string) ($credential->attestation_format ?: 'none'),
            new EmptyTrustPath,
            $aaguid,
            $credentialPublicKey,
            (string) $credential->authenticatable_id,
            (int) ($credential->counter ?? 0),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
