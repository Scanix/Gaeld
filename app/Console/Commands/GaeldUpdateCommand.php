<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GaeldUpdateCommand extends Command
{
    protected $signature = 'gaeld:update
        {--to= : Exact release version to install}
        {--skip-migrations : Unsupported: migrations are part of the verified release}
        {--skip-cache : Unsupported: caches are part of the verified release}';

    protected $description = 'Update Gäld through the managed host installer';

    public function handle(): int
    {
        $this->components->error('Run ./gaeld update --to X.Y.Z on the host. The installer verifies the release, drains writers and creates an encrypted recovery backup before applying migrations.');

        return self::FAILURE;
    }
}
