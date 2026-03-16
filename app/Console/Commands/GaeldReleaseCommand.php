<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GaeldReleaseCommand extends Command
{
    protected $signature = 'gaeld:release
        {edition=community : Edition to build (community or saas)}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Prepare a release build for Community or SaaS edition';

    /**
     * Feature flags for each edition.
     * Community: SaaS-only features disabled.
     * SaaS: all features enabled.
     */
    private const EDITION_FLAGS = [
        'community' => [
            'FEATURE_BANK_SYNC' => 'false',
            'FEATURE_SAAS' => 'false',
            'FEATURE_AUTOMATION' => 'false',
            'FEATURE_MULTI_CURRENCY' => 'false',
            'FEATURE_API_ACCESS' => 'false',
        ],
        'saas' => [
            'FEATURE_BANK_SYNC' => 'true',
            'FEATURE_SAAS' => 'true',
            'FEATURE_AUTOMATION' => 'true',
            'FEATURE_MULTI_CURRENCY' => 'true',
            'FEATURE_API_ACCESS' => 'true',
        ],
    ];

    public function handle(): int
    {
        $edition = strtolower($this->argument('edition'));

        if (! isset(self::EDITION_FLAGS[$edition])) {
            $this->components->error("Unknown edition: {$edition}. Use 'community' or 'saas'.");

            return self::FAILURE;
        }

        $flags = self::EDITION_FLAGS[$edition];

        $this->components->info("Preparing {$edition} edition release");
        $this->newLine();

        // Show flag configuration
        foreach ($flags as $key => $value) {
            $status = $value === 'true' ? '<fg=green>enabled</>' : '<fg=yellow>disabled</>';
            $this->components->twoColumnDetail($key, $status);
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        // Update .env file
        $this->components->task('Updating .env feature flags', function () use ($flags) {
            $envPath = base_path('.env');

            if (! File::exists($envPath)) {
                return false;
            }

            $contents = File::get($envPath);

            foreach ($flags as $key => $value) {
                $pattern = "/^{$key}=.*/m";

                if (preg_match($pattern, $contents)) {
                    $contents = preg_replace($pattern, "{$key}={$value}", $contents);
                } else {
                    $contents .= "\n{$key}={$value}";
                }
            }

            File::put($envPath, $contents);
        });

        // Optimize for production
        $this->components->task('Optimizing for production', function () {
            $this->callSilently('config:cache');
            $this->callSilently('route:cache');
            $this->callSilently('view:cache');
            $this->callSilently('event:cache');
        });

        $this->newLine();
        $this->components->info("Release prepared for {$edition} edition.");

        return self::SUCCESS;
    }
}
