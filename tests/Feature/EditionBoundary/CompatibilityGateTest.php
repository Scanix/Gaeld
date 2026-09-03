<?php

namespace Tests\Feature\EditionBoundary;

use App\Support\EditionCompatibility;
use App\Support\EditionReleasePair;
use Tests\TestCase;

class CompatibilityGateTest extends TestCase
{
    public function test_compatible_release_pair_is_accepted(): void
    {
        $gate = new EditionReleasePair(new EditionCompatibility);

        $this->assertTrue($gate->isCompatible([
            'contract_version' => '1.0.0',
            'ee_version' => '2.9.18',
        ], 'v2.9.18'));
    }

    public function test_incompatible_release_pair_is_rejected_before_activation(): void
    {
        $gate = new EditionReleasePair(new EditionCompatibility);

        $this->assertSame(
            'unexpected-ee-version',
            $gate->failureReason([
                'contract_version' => '1.0.0',
                'ee_version' => '2.9.17',
            ], '2.9.18')
        );
        $this->assertSame(
            'unsupported-contract-version',
            $gate->failureReason([
                'contract_version' => '2.0.0',
                'ee_version' => '2.9.18',
            ], '2.9.18')
        );
    }
}
