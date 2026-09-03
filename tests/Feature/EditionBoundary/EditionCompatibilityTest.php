<?php

namespace Tests\Feature\EditionBoundary;

use App\Support\EditionCompatibility;
use Tests\TestCase;

class EditionCompatibilityTest extends TestCase
{
    public function test_compatible_ee_metadata_is_accepted(): void
    {
        $compatibility = new EditionCompatibility;

        $this->assertTrue($compatibility->isCompatible([
            'contract_version' => '1.0.0',
            'ee_version' => '2.4.2',
        ]));
        $this->assertNull($compatibility->incompatibilityReason([
            'contract_version' => '1.0.0',
            'ee_version' => '2.4.2',
        ]));
    }

    public function test_missing_or_malformed_metadata_fails_closed_with_non_sensitive_reasons(): void
    {
        $compatibility = new EditionCompatibility;

        $reasons = [
            $compatibility->incompatibilityReason([]),
            $compatibility->incompatibilityReason(['contract_version' => '1.0.0']),
            $compatibility->incompatibilityReason([
                'contract_version' => '1.0.0',
                'ee_version' => 'not-a-version',
            ]),
        ];

        $this->assertSame([
            'missing-contract-version',
            'missing-ee-version',
            'invalid-ee-version',
        ], $reasons);
        $this->assertFalse($compatibility->isCompatible([]));
    }

    public function test_unsupported_contract_and_ee_versions_are_rejected(): void
    {
        $compatibility = new EditionCompatibility;

        $this->assertSame(
            'unsupported-contract-version',
            $compatibility->incompatibilityReason([
                'contract_version' => '2.0.0',
                'ee_version' => '2.4.2',
            ])
        );
        $this->assertSame(
            'unsupported-ee-version',
            $compatibility->incompatibilityReason([
                'contract_version' => '1.0.0',
                'ee_version' => '3.0.0',
            ])
        );
        $this->assertTrue($compatibility->isCompatible([
            'contract_version' => '1.0.0',
            'ee_version' => '2.9.9',
        ]));
    }
}
