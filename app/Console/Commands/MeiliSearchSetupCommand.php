<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MeiliSearchSetupCommand extends Command
{
    protected $signature = 'gaeld:meilisearch:setup
        {--host= : MeiliSearch host URL (default from config)}
        {--master-key= : MeiliSearch master key}';

    protected $description = 'Create MeiliSearch API keys and configure index settings for Gäld';

    public function handle(): int
    {
        $host = $this->option('host') ?: config('scout.meilisearch.host');
        $masterKey = $this->option('master-key') ?: config('scout.meilisearch.key');

        if (! $masterKey) {
            $this->components->error('Master key is required. Pass --master-key or set MEILISEARCH_KEY.');

            return self::FAILURE;
        }

        $this->components->info('Setting up MeiliSearch for Gäld');

        // 1. Create search-only API key for the docs frontend
        $this->components->task('Creating docs search-only API key', function () use ($host, $masterKey) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$masterKey}",
                'Content-Type' => 'application/json',
            ])->post("{$host}/keys", [
                'name' => 'Gaeld Docs Search Key',
                'description' => 'Public search-only key for documentation site',
                'actions' => ['search'],
                'indexes' => ['gaeld-docs'],
                'expiresAt' => null,
            ]);

            if ($response->successful()) {
                $key = $response->json('key');
                $this->newLine();
                $this->components->info("Docs search key: {$key}");
                $this->components->info('Set MEILISEARCH_SEARCH_API_KEY in your docs build environment.');
            } else {
                $this->newLine();
                $this->components->warn('Docs key may already exist: '.$response->body());
            }
        });

        // 2. Create search-only API key for the API (tenant-filtered)
        $this->components->task('Creating API search-only key', function () use ($host, $masterKey) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$masterKey}",
                'Content-Type' => 'application/json',
            ])->post("{$host}/keys", [
                'name' => 'Gaeld API Search Key',
                'description' => 'Backend search key with tenant-filtered access',
                'actions' => ['search', 'documents.add', 'documents.delete', 'indexes.create', 'indexes.update', 'indexes.get'],
                'indexes' => ['invoices', 'contacts', 'expenses'],
                'expiresAt' => null,
            ]);

            if ($response->successful()) {
                $key = $response->json('key');
                $this->newLine();
                $this->components->info("API key: {$key}");
                $this->components->info('Set MEILISEARCH_KEY in the API .env file.');
            } else {
                $this->newLine();
                $this->components->warn('API key may already exist: '.$response->body());
            }
        });

        // 3. Configure index settings with filterable attributes for tenant isolation
        $indexSettings = [
            'invoices' => [
                'filterableAttributes' => ['organization_id', 'status', 'currency'],
                'sortableAttributes' => ['total', 'number'],
                'searchableAttributes' => ['number', 'customer_name', 'status', 'currency'],
            ],
            'contacts' => [
                'filterableAttributes' => ['organization_id'],
                'sortableAttributes' => ['name'],
                'searchableAttributes' => ['name', 'email', 'city', 'vat_number', 'default_expense_category', 'contact_persons'],
            ],
            'expenses' => [
                'filterableAttributes' => ['organization_id', 'status', 'category'],
                'sortableAttributes' => ['amount', 'date'],
                'searchableAttributes' => ['description', 'vendor', 'category'],
            ],
        ];

        foreach ($indexSettings as $index => $settings) {
            $this->components->task("Configuring index [{$index}]", function () use ($host, $masterKey, $index, $settings) {
                Http::withHeaders([
                    'Authorization' => "Bearer {$masterKey}",
                    'Content-Type' => 'application/json',
                ])->patch("{$host}/indexes/{$index}/settings", $settings);
            });
        }

        $this->newLine();
        $this->components->info('MeiliSearch setup complete.');
        $this->components->info('Next steps:');
        $this->line('  1. Set SCOUT_DRIVER=meilisearch in the API .env');
        $this->line('  2. Set MEILISEARCH_HOST and MEILISEARCH_KEY in the API .env');
        $this->line('  3. Run: php artisan scout:import to index existing data');
        $this->line('  4. Set MEILISEARCH_SEARCH_API_KEY in your docs build env');

        return self::SUCCESS;
    }
}
