<?php

namespace App\Support\Contracts;

interface EditionCompatibility
{
    public function contractVersion(): string;

    /**
     * @param  array<string, mixed>  $eeMetadata
     */
    public function isCompatible(array $eeMetadata): bool;

    /**
     * Return a non-sensitive diagnostic when EE metadata cannot be accepted.
     *
     * @param  array<string, mixed>  $eeMetadata
     */
    public function incompatibilityReason(array $eeMetadata): ?string;
}
