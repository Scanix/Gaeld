<?php

namespace Tests\Unit\Commands;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class GaeldRedisSnapshotCommandTest extends TestCase
{
    public function test_it_refuses_to_export_while_writers_can_run(): void
    {
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturnFalse();
        $this->artisan('gaeld:redis-snapshot', ['operation' => 'export', '--path' => '-'])->assertFailed();
    }

    public function test_it_restores_queue_payloads_without_touching_other_installations(): void
    {
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturnTrue();
        $prefix = 'gaeld-r1-test-'.bin2hex(random_bytes(6)).':';
        $client = Redis::connection('default')->client();
        $horizon = Redis::connection('horizon')->client();
        $previous = $client->getOption(\Redis::OPT_PREFIX);
        $previousHorizon = $horizon->getOption(\Redis::OPT_PREFIX);
        $client->setOption(\Redis::OPT_PREFIX, $prefix);
        $horizon->setOption(\Redis::OPT_PREFIX, $prefix.'horizon:');
        $path = tempnam(sys_get_temp_dir(), 'gaeld-redis-');
        try {
            $client->rPush('queues:default', '{"job":"preserved"}');
            $client->rawCommand('SET', $prefix.'unrelated-external', 'outside');
            $externalKey = 'other-installation:'.bin2hex(random_bytes(8));
            $client->rawCommand('SET', $externalKey, 'untouched');
            $this->artisan('gaeld:redis-snapshot', ['operation' => 'export', '--path' => $path])->assertSuccessful();
            $client->del('queues:default');
            $client->rPush('queues:default', '{"job":"newer"}');
            $this->artisan('gaeld:redis-snapshot', ['operation' => 'restore', '--path' => $path])->assertSuccessful();
            $this->assertSame(['{"job":"preserved"}'], $client->lRange('queues:default', 0, -1));
            $this->assertSame('untouched', $client->rawCommand('GET', $externalKey));
        } finally {
            foreach (['queues:default', 'unrelated-external'] as $key) {
                $client->del($key);
            }
            if (isset($externalKey)) {
                $client->rawCommand('DEL', $externalKey);
            }
            $client->setOption(\Redis::OPT_PREFIX, $previous);
            $horizon->setOption(\Redis::OPT_PREFIX, $previousHorizon);
            @unlink($path);
        }
    }
}
