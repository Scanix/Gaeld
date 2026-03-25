<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MeiliSearchReindexCommand extends Command
{
    protected $signature = 'gaeld:meilisearch:reindex
        {model? : Specific model to reindex (invoices, customers, suppliers, expenses)}
        {--flush : Flush existing index before re-importing}';

    protected $description = 'Re-index searchable models into MeiliSearch';

    private const MODELS = [
        'invoices' => \App\Domains\Invoicing\Models\Invoice::class,
        'customers' => \App\Domains\Contacts\Models\Customer::class,
        'suppliers' => \App\Domains\Contacts\Models\Supplier::class,
        'expenses' => \App\Domains\Expenses\Models\Expense::class,
    ];

    public function handle(): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->components->error('SCOUT_DRIVER must be set to "meilisearch".');

            return self::FAILURE;
        }

        $model = $this->argument('model');
        $models = $model ? [$model => self::MODELS[$model] ?? null] : self::MODELS;

        if ($model && ! isset(self::MODELS[$model])) {
            $this->components->error("Unknown model: {$model}. Available: ".implode(', ', array_keys(self::MODELS)));

            return self::FAILURE;
        }

        $this->components->info('Re-indexing models into MeiliSearch');

        // Sync index settings first
        $this->components->task('Syncing index settings', function () {
            $this->callSilently('scout:sync-index-settings');
        });

        foreach ($models as $name => $class) {
            if ($this->option('flush')) {
                $this->components->task("Flushing [{$name}]", function () use ($class) {
                    $this->callSilently('scout:flush', ['model' => $class]);
                });
            }

            $this->components->task("Importing [{$name}]", function () use ($class) {
                $this->callSilently('scout:import', ['model' => $class]);
            });
        }

        $this->newLine();
        $this->components->info('Re-indexing complete.');

        return self::SUCCESS;
    }
}
