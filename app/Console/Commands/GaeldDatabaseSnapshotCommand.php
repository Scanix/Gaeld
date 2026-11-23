<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Spatie\DbDumper\Databases\PostgreSql;
use Symfony\Component\Process\Process;

class GaeldDatabaseSnapshotCommand extends Command
{
    protected $signature = 'gaeld:database-snapshot';

    protected $description = 'Stream a PostgreSQL recovery snapshot using the installed Spatie backup component';

    public function handle(): int
    {
        if (! app()->isDownForMaintenance()) {
            $this->components->error('Use the host backup command to stop writers before taking a recovery snapshot.');

            return self::FAILURE;
        }

        $connection = config('database.connections.pgsql');
        foreach (['host', 'username', 'database'] as $key) {
            if (! is_string($connection[$key]) || ! preg_match('/^[a-zA-Z0-9_.:-]+$/', $connection[$key])) {
                throw new RuntimeException('Managed PostgreSQL snapshot requires literal host and database identifiers.');
            }
        }

        $version = (new Process(['pg_dump', '--version']))->mustRun()->getOutput();
        if (! preg_match('/PostgreSQL\) 16\./', $version)) {
            throw new RuntimeException('Managed recovery requires PostgreSQL 16 client tools matching the supported database major version.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'gaeld-db-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate a private recovery dump file.');
        }

        try {
            PostgreSql::create()
                ->setHost($connection['host'])
                ->setPort((int) $connection['port'])
                ->setDbName($connection['database'])
                ->setUserName($connection['username'])
                ->setPassword((string) $connection['password'])
                ->addExtraOption('--format=custom')
                ->addExtraOption('--no-owner')
                ->addExtraOption('--no-acl')
                ->dumpToFile($temporary);

            $stream = fopen($temporary, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Cannot read completed recovery dump.');
            }
            try {
                if (fpassthru($stream) !== filesize($temporary)) {
                    throw new RuntimeException('Cannot stream recovery dump.');
                }
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($temporary);
        }

        return self::SUCCESS;
    }
}
