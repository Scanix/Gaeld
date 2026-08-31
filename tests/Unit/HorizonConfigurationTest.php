<?php

namespace Tests\Unit;

use Tests\TestCase;

class HorizonConfigurationTest extends TestCase
{
    public function test_staging_horizon_environment_uses_default_supervisors(): void
    {
        $environments = config('horizon.environments');

        $this->assertArrayHasKey('staging', $environments);
        $this->assertSame([], $environments['staging']);
        $this->assertArrayHasKey('supervisor-1', config('horizon.defaults'));
        $this->assertArrayHasKey('supervisor-ocr', config('horizon.defaults'));
    }
}
