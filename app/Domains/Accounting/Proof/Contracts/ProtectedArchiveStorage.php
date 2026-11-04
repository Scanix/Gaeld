<?php

namespace App\Domains\Accounting\Proof\Contracts;

interface ProtectedArchiveStorage
{
    /** @return array<string, mixed> */
    public function deposit(string $directory, string $retainUntil): array;

    /** @param array<string, mixed> $receipt */
    public function verify(string $directory, array $receipt): void;

    /** @return list<array<string, mixed>> */
    public function inventory(?string $organizationId = null): array;

    /** @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public function protect(array $receipt, string $retainUntil, bool $legalHold): array;
}
