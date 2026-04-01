<?php

namespace App\Domains\Migration\Providers;

use App\Domains\Migration\Importers\AccountImporter;
use App\Domains\Migration\Importers\ContactImporter;
use App\Domains\Migration\Importers\ExpenseImporter;
use App\Domains\Migration\Importers\FixedAssetImporter;
use App\Domains\Migration\Importers\InvoiceImporter;
use App\Domains\Migration\Importers\JournalEntryImporter;
use App\Domains\Migration\Importers\OpeningBalanceImporter;
use App\Domains\Migration\Importers\YearEndClosingImporter;
use App\Domains\Migration\Mappers\FuzzyNameAccountMapper;
use App\Domains\Migration\Mappers\NumberPatternAccountMapper;
use App\Domains\Migration\Parsers\AbacusParser;
use App\Domains\Migration\Parsers\BananaParser;
use App\Domains\Migration\Parsers\BexioParser;
use App\Domains\Migration\Parsers\GenericCsvParser;
use App\Domains\Migration\Services\MigrationRegistry;
use Illuminate\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MigrationRegistry::class, function ($app) {
            $registry = new MigrationRegistry;

            // Parsers
            $registry->registerParser($app->make(BexioParser::class));
            // WIP: Banana and Abacus parsers are not yet validated for production use
            // $registry->registerParser($app->make(BananaParser::class));
            // $registry->registerParser($app->make(AbacusParser::class));
            $registry->registerParser($app->make(GenericCsvParser::class));

            // Importers
            $registry->registerImporter($app->make(AccountImporter::class));
            $registry->registerImporter($app->make(ContactImporter::class));
            $registry->registerImporter($app->make(OpeningBalanceImporter::class));
            $registry->registerImporter($app->make(JournalEntryImporter::class));
            $registry->registerImporter($app->make(InvoiceImporter::class));
            $registry->registerImporter($app->make(ExpenseImporter::class));
            $registry->registerImporter($app->make(FixedAssetImporter::class));
            $registry->registerImporter($app->make(YearEndClosingImporter::class));

            // Account mappers
            $registry->registerMapper($app->make(NumberPatternAccountMapper::class));
            $registry->registerMapper($app->make(FuzzyNameAccountMapper::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
