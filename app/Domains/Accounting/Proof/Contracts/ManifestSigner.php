<?php

namespace App\Domains\Accounting\Proof\Contracts;

interface ManifestSigner
{
    /** @return array<string, mixed> */
    public function sign(string $manifest, string $evidenceDirectory): array;
}
