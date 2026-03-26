<?php

namespace App\Console\Commands;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GaeldInstallCommand extends Command
{
    public function __construct(
        private readonly UserService $userService,
        private readonly OrganizationService $organizationService,
        private readonly OrganizationSetupService $organizationSetupService,
    ) {
        parent::__construct();
    }

    protected $signature = 'gaeld:install
        {--demo : Seed demo data for testing}';

    protected $description = 'Install Gäld: run migrations, seed accounts/VAT, create admin user and organization';

    public function handle(): int
    {
        $this->components->info('Installing Gäld — Swiss Accounting Platform');
        $this->newLine();

        // Step 1: Migrations
        $this->components->task('Running database migrations', function () {
            $this->callSilently('migrate', ['--force' => true]);
        });

        // Step 2: Check if already installed
        if (Organization::exists()) {
            $this->components->warn('Gäld is already installed. Use gaeld:update to apply updates.');

            return self::SUCCESS;
        }

        // Step 3: Collect admin user info
        if ($this->option('no-interaction')) {
            $adminName = 'Admin';
            $adminEmail = 'admin@gaeld.local';
            $adminPassword = Str::random(16);
            $orgName = 'My Company';
            $currency = 'CHF';
            $locale = 'en';
        } else {
            $adminName = $this->ask('Admin name', 'Admin');
            $adminEmail = $this->ask('Admin email', 'admin@gaeld.local');
            $adminPassword = $this->secret('Admin password (min 8 chars)') ?? Str::random(16);
            $orgName = $this->ask('Organization name', 'My Company');
            $currency = $this->choice('Default currency', ['CHF', 'EUR', 'USD', 'GBP'], 0);
            $locale = $this->choice('Default language', ['en', 'fr', 'de', 'it'], 0);
        }

        // Step 4: Create admin + organization
        $this->components->task('Creating admin user and organization', function () use (
            $adminName, $adminEmail, $adminPassword, $orgName, $currency, $locale
        ) {
            DB::transaction(function () use ($adminName, $adminEmail, $adminPassword, $orgName, $currency, $locale) {
                $user = $this->userService->create(new CreateUserData(
                    name: $adminName,
                    email: $adminEmail,
                    password: $adminPassword,
                    locale: $locale,
                    emailVerifiedAt: now(),
                ));

                $this->organizationService->create($user, new CreateOrganizationData(
                    name: $orgName,
                    legalName: $orgName,
                    currency: $currency,
                    locale: $locale,
                    country: 'CH',
                ));
            });
        });

        // Step 5: Seed chart of accounts
        $this->components->task('Seeding Swiss chart of accounts and VAT rates', function () {
            $org = \App\Domains\Organizations\Models\Organization::latest()->first();
            $this->organizationSetupService->seedSwissDefaults($org);
        });

        // Step 6: Demo data (optional)
        if ($this->option('demo')) {
            $this->components->task('Seeding demo data', function () {
                $this->callSilently('db:seed', [
                    '--class' => 'Database\\Seeders\\DemoDataSeeder',
                    '--force' => true,
                ]);
            });
        }

        // Step 7: Cache config
        $this->components->task('Caching configuration', function () {
            $this->callSilently('config:cache');
            $this->callSilently('route:cache');
            $this->callSilently('view:cache');
        });

        $this->newLine();
        $this->components->info('Gäld installed successfully!');
        $this->newLine();

        $this->components->twoColumnDetail('URL', config('app.url'));
        $this->components->twoColumnDetail('Admin Email', $adminEmail);
        if ($this->option('no-interaction')) {
            $this->components->twoColumnDetail('Admin Password', $adminPassword);
        }
        $this->components->twoColumnDetail('Currency', $currency);

        $this->newLine();
        $this->components->info('Run `php artisan gaeld:install --demo` to add sample data.');

        return self::SUCCESS;
    }
}
