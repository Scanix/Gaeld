<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, mixed> */
    private array $initialFeatureConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialFeatureConfig = config('features', []);
    }

    protected function tearDown(): void
    {
        config(['features' => $this->initialFeatureConfig]);

        parent::tearDown();
    }
}
