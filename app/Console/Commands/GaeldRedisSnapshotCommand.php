<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class GaeldRedisSnapshotCommand extends Command
{
    protected $signature = 'gaeld:redis-snapshot {operation : export or restore} {--path= : Local recovery snapshot path}';

    protected $description = 'Preserve installation Redis queues during a managed recovery operation';

    public function handle(): int
    {
        if (! app()->isDownForMaintenance()) {
            $this->components->error('Enter maintenance and stop all writers using the host installer first.');

            return self::FAILURE;
        }

        $path = (string) $this->option('path');
        if ($path === '' || ! in_array($this->argument('operation'), ['export', 'restore'], true)) {
            return self::INVALID;
        }

        $snapshot = ['schema' => 1, 'created_at_ms' => (int) floor(microtime(true) * 1000), 'connections' => []];
        if ($this->argument('operation') === 'restore') {
            $encoded = file_get_contents($path === '-' ? 'php://stdin' : $path);
            if ($encoded === false) {
                throw new RuntimeException('Cannot read Redis recovery snapshot.');
            }
            $snapshot = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        }

        if (($snapshot['schema'] ?? null) !== 1) {
            throw new RuntimeException('Unknown Redis recovery snapshot format.');
        }

        foreach (['default', 'horizon'] as $connection) {
            $client = Redis::connection($connection)->client();
            if (! $client instanceof \Redis) {
                throw new RuntimeException('Managed Redis recovery requires the PhpRedis client and a non-clustered instance.');
            }

            /** @var mixed $prefix PhpRedis returns a string for OPT_PREFIX. */
            $prefix = $client->getOption(\Redis::OPT_PREFIX);
            if (! is_string($prefix) || $prefix === '' || preg_match('/[*?\[\\\\]/', $prefix)) {
                throw new RuntimeException('A non-empty literal Redis installation prefix is required.');
            }

            if ($this->argument('operation') === 'export') {
                $records = [];
                foreach ($this->keys($client, $prefix) as $key) {
                    $value = $client->rawCommand('DUMP', $key);
                    $ttl = $client->rawCommand('PTTL', $key);
                    if ($value !== false && $ttl !== -2) {
                        $records[] = ['key' => $key, 'ttl' => $ttl, 'value' => base64_encode($value)];
                    }
                }
                $snapshot['connections'][$connection] = ['prefix' => $prefix, 'records' => $records];

                continue;
            }

            $saved = $snapshot['connections'][$connection] ?? null;
            if (! is_array($saved) || ($saved['prefix'] ?? null) !== $prefix) {
                throw new RuntimeException('Redis prefix differs from the recovery snapshot.');
            }
            foreach ($saved['records'] as $record) {
                if (! str_starts_with($record['key'], $prefix) || base64_decode($record['value'], true) === false) {
                    throw new RuntimeException('Invalid Redis recovery record.');
                }
            }
            foreach ($this->keys($client, $prefix) as $key) {
                $client->rawCommand('DEL', $key);
            }
            $elapsed = max(0, (int) floor(microtime(true) * 1000) - $snapshot['created_at_ms']);
            foreach ($saved['records'] as $record) {
                $ttl = $record['ttl'] === -1 ? 0 : $record['ttl'] - $elapsed;
                if ($record['ttl'] !== -1 && $ttl <= 0) {
                    continue;
                }
                if ($client->rawCommand('RESTORE', $record['key'], $ttl, base64_decode($record['value'], true), 'REPLACE') === false) {
                    throw new RuntimeException('Redis rejected a recovery record.');
                }
            }
        }

        if ($this->argument('operation') === 'export') {
            if ($path === '-') {
                $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            if (file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new RuntimeException('Cannot write Redis recovery snapshot.');
            }
            chmod($path, 0600);
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function keys(\Redis $client, string $prefix): array
    {
        $cursor = '0';
        $keys = [];
        do {
            $response = $client->rawCommand('SCAN', $cursor, 'MATCH', $prefix.'*', 'COUNT', 1000);
            if (! is_array($response) || count($response) !== 2) {
                throw new RuntimeException('Cannot enumerate Redis recovery records.');
            }
            [$cursor, $batch] = $response;
            $keys = array_merge($keys, $batch);
        } while ((string) $cursor !== '0');

        return array_values(array_unique($keys));
    }
}
