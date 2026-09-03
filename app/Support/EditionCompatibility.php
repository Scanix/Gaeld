<?php

namespace App\Support;

use App\Support\Contracts\EditionCompatibility as EditionCompatibilityContract;

final class EditionCompatibility implements EditionCompatibilityContract
{
    public const CONTRACT_VERSION = '1.0.0';

    private const MINIMUM_EE_VERSION = '2.0.0';

    private const MAXIMUM_EE_VERSION = '3.0.0';

    public function contractVersion(): string
    {
        return self::CONTRACT_VERSION;
    }

    /**
     * @param  array<string, mixed>  $eeMetadata
     */
    public function isCompatible(array $eeMetadata): bool
    {
        return $this->incompatibilityReason($eeMetadata) === null;
    }

    /**
     * @param  array<string, mixed>  $eeMetadata
     */
    public function incompatibilityReason(array $eeMetadata): ?string
    {
        $contractVersion = $eeMetadata['contract_version'] ?? null;
        if (! is_string($contractVersion) || $contractVersion === '') {
            return 'missing-contract-version';
        }

        if ($contractVersion !== self::CONTRACT_VERSION) {
            return 'unsupported-contract-version';
        }

        $eeVersion = $eeMetadata['ee_version'] ?? null;
        if (! is_string($eeVersion) || $eeVersion === '') {
            return 'missing-ee-version';
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $eeVersion)) {
            return 'invalid-ee-version';
        }

        if (version_compare($eeVersion, self::MINIMUM_EE_VERSION, '<')
            || version_compare($eeVersion, self::MAXIMUM_EE_VERSION, '>=')
        ) {
            return 'unsupported-ee-version';
        }

        return null;
    }
}
