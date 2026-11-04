<?php

namespace App\Domains\Accounting\Proof\Contracts;

interface TimestampAuthority
{
    /** @return array<string, mixed> */
    public function timestamp(string $source, string $evidencePrefix): array;
}
