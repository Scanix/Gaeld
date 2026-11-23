<?php

namespace Tests\Unit;

use Tests\TestCase;

class HorizonConfigurationTest extends TestCase
{
    public function test_staging_horizon_environment_uses_default_supervisors(): void
    {
        $environments = config('horizon.environments');

        $this->assertArrayHasKey('staging', $environments);
        $this->assertSame(['supervisor-archives' => ['maxProcesses' => 1]], $environments['staging']);
        $this->assertArrayHasKey('supervisor-1', config('horizon.defaults'));
        $this->assertArrayHasKey('supervisor-ocr', config('horizon.defaults'));
        $archives = config('horizon.defaults.supervisor-archives');
        $this->assertSame(['archives'], $archives['queue']);
        $this->assertGreaterThan($archives['timeout'], config('queue.connections.archives.retry_after'));
    }
}
