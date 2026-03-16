<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GaeldUpdateCommand extends Command
{
    protected $signature = 'gaeld:update
        {--skip-migrations : Skip running migrations}
        {--skip-cache : Skip cache clearing}';

    protected $description = 'Update Gäld: run pending migrations, clear caches, restart queue workers';

    public function handle(): int
    {
        $this->components->info('Updating Gäld');
        $this->newLine();

        // Step 1: Maintenance mode
        $this->components->task('Entering maintenance mode', function () {
            $this->callSilently('down', ['--retry' => 30]);
        });

        // Step 2: Migrations
        if (! $this->option('skip-migrations')) {
            $this->components->task('Running pending migrations', function () {
                $this->callSilently('migrate', ['--force' => true]);
            });
        } else {
            $this->components->twoColumnDetail('Migrations', '<fg=yellow>skipped</>');
        }

        // Step 3: Clear caches
        if (! $this->option('skip-cache')) {
            $this->components->task('Clearing application caches', function () {
                $this->callSilently('cache:clear');
                $this->callSilently('config:clear');
                $this->callSilently('route:clear');
                $this->callSilently('view:clear');
                $this->callSilently('event:clear');
            });

            $this->components->task('Rebuilding caches', function () {
                $this->callSilently('config:cache');
                $this->callSilently('route:cache');
                $this->callSilently('view:cache');
                $this->callSilently('event:cache');
            });
        } else {
            $this->components->twoColumnDetail('Cache management', '<fg=yellow>skipped</>');
        }

        // Step 4: Restart queue workers
        $this->components->task('Restarting queue workers', function () {
            $this->callSilently('queue:restart');
        });

        // Step 5: Leave maintenance mode
        $this->components->task('Leaving maintenance mode', function () {
            $this->callSilently('up');
        });

        $this->newLine();
        $this->components->info('Gäld updated successfully.');

        return self::SUCCESS;
    }
}
