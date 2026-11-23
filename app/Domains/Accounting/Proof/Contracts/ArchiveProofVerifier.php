<?php

namespace App\Domains\Accounting\Proof\Contracts;

interface ArchiveProofVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $directory, ?string $expectedManifestHash = null): array;
}
