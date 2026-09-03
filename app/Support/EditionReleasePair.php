<?php

namespace App\Support;

use App\Support\Contracts\EditionCompatibility;

final readonly class EditionReleasePair
{
    public function __construct(
        private EditionCompatibility $compatibility,
    ) {}

    /**
     * @param  array<string, mixed>  $eeMetadata
     */
    public function isCompatible(array $eeMetadata, ?string $expectedEeVersion = null): bool
    {
        return $this->failureReason($eeMetadata, $expectedEeVersion) === null;
    }

    /**
     * @param  array<string, mixed>  $eeMetadata
     */
    public function failureReason(array $eeMetadata, ?string $expectedEeVersion = null): ?string
    {
        $reason = $this->compatibility->incompatibilityReason($eeMetadata);
        if ($reason !== null) {
            return $reason;
        }

        $normalizedExpectedVersion = $expectedEeVersion !== null
            ? ltrim($expectedEeVersion, 'v')
            : null;

        if ($normalizedExpectedVersion !== null && ($eeMetadata['ee_version'] ?? null) !== $normalizedExpectedVersion) {
            return 'unexpected-ee-version';
        }

        return null;
    }
}
