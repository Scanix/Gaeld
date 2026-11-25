<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\ArchiveProofVerifier;
use Gaeld\ArchiveVerifier\Verifier;

class StandaloneArchiveVerifier implements ArchiveProofVerifier
{
    public function verify(string $directory, ?string $expectedManifestHash = null): array
    {
        return $this->verifier()->verify($directory, $expectedManifestHash);
    }

    public function verifier(): Verifier
    {
        require_once base_path('scripts/archive-verifier/Verifier.php');

        return new Verifier([
            'openssl' => (string) config('archiving.openssl'),
            'verapdf' => (string) config('archiving.pdfa.verapdf'),
            'signer_trust' => (string) config('archiving.signing.trust_store'),
            'signer_fingerprint' => (string) config('archiving.signing.allowed_fingerprint'),
            'tsa_trust' => (string) config('archiving.timestamp.trust_store'),
        ]);
    }
}
