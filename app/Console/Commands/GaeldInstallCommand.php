<?php

namespace App\Console\Commands;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
        {--demo : Seed demo data for testing}
        {--admin-secret-file= : Protected JSON file with administrator and organization details}';

    protected $description = 'Install Gäld: run migrations, seed accounts/VAT, create admin user and organization';

    public function handle(): int
    {
        $this->components->info('Installing Gäld — Swiss Accounting Platform');
        $this->newLine();

        // Step 0: Generate APP_KEY if missing
        if (empty(config('app.key'))) {
            if (app()->isProduction() || Schema::hasTable('migrations')) {
                $this->components->error('APP_KEY is missing. Recover the original key for an existing installation, or use ./gaeld install for a fresh production installation.');

                return self::FAILURE;
            }
            $this->components->task('Generating application key', function () {
                $this->runRequired('key:generate', ['--force' => true]);
            });
        }

        if (Schema::hasTable('organizations') && Organization::exists()) {
            $this->components->warn('Gäld is already installed. Use ./gaeld update --to X.Y.Z to apply updates.');

            return app()->isProduction() ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('demo') && app()->isProduction()) {
            $this->components->error('Demo data cannot be installed in production.');

            return self::FAILURE;
        }

        $admin = null;
        if ($secretFile = $this->option('admin-secret-file')) {
            try {
                if (! is_file($secretFile) || filesize($secretFile) > 65536 || (fileperms($secretFile) & 0077) !== 0) {
                    throw new \RuntimeException('Administrator secret file must be a private regular file (0400 or 0600), at most 64 KiB.');
                }
                $secretContents = file_get_contents($secretFile);
                if ($secretContents === false) {
                    throw new \RuntimeException('Administrator secret file could not be read.');
                }
                $admin = json_decode($secretContents, true, 16, JSON_THROW_ON_ERROR);
                if (! is_array($admin)) {
                    throw new \RuntimeException('Administrator secret file must contain a JSON object.');
                }
                $validator = Validator::make($admin, [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
                    'organization' => ['required', 'string', 'max:255'],
                    'currency' => ['required', Rule::in(config('accounting.supported_currencies'))],
                    'locale' => ['required', Rule::in(config('accounting.supported_locales'))],
                ]);
                if ($validator->fails()) {
                    throw new \RuntimeException('Invalid administrator secret fields: '.implode(', ', array_keys($validator->errors()->toArray())));
                }
                $admin = $validator->validated();
            } catch (\Throwable $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        } elseif ($this->option('no-interaction') && ! $this->option('demo')) {
            $this->components->error('Provide --admin-secret-file for unattended installation, or use the interactive installer.');

            return self::FAILURE;
        }

        // Step 1: Migrations
        $this->components->task('Running database migrations', function () {
            $this->runRequired('migrate', ['--force' => true]);
        });

        // Step 2: Check if already installed
        if (Organization::exists()) {
            $this->components->warn('Gäld is already installed. Use gaeld:update to apply updates.');

            return self::SUCCESS;
        }

        // Step 3: Collect admin user info
        if ($admin !== null) {
            $adminName = $admin['name'];
            $adminEmail = $admin['email'];
            $adminPassword = $admin['password'];
            $orgName = $admin['organization'];
            $currency = $admin['currency'];
            $locale = $admin['locale'];
        } elseif ($this->option('no-interaction')) {
            $adminName = 'Admin';
            $adminEmail = 'admin@gaeld.local';
            $adminPassword = Str::random(16);
            $orgName = 'My Company';
            $currency = config('accounting.default_currency');
            $locale = 'en';
        } else {
            $adminName = $this->askValid('Admin name', 'Admin', [
                'required', 'string', 'max:255',
            ]);

            $adminEmail = $this->askValid('Admin email', 'admin@gaeld.local', [
                'required', 'string', 'email', 'max:255', 'unique:users,email',
            ]);

            $adminPassword = $this->secretValid('Admin password (min 12 characters, letters and numbers)', [
                'required', 'string', Password::min(12)->letters()->numbers(),
            ]);

            $orgName = $this->askValid('Organization name', 'My Company', [
                'required', 'string', 'max:255',
            ]);

            if (config('features.multi_currency')) {
                $currency = $this->choice('Default currency', config('accounting.supported_currencies'), 0);
            } else {
                $currency = 'CHF';
            }

            $locale = $this->choice('Default language', config('accounting.supported_locales'), 0);
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
                $user->markEmailAsVerified();

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
            $org = Organization::latest()->first();
            $this->organizationSetupService->seedChartOfAccounts($org, 'swiss_sme');
        });

        // Step 6: Demo data (optional)
        if ($this->option('demo')) {
            $this->components->task('Seeding demo data', function () {
                $this->runRequired('db:seed', [
                    '--class' => 'Database\\Seeders\\DemoDataSeeder',
                    '--force' => true,
                ]);
            });
        }

        // Step 7: Cache config
        $this->components->task('Caching configuration', function () {
            $this->runRequired('config:cache');
            $this->runRequired('route:cache');
            $this->runRequired('view:cache');
        });

        $this->newLine();
        $this->components->info('Gäld installed successfully!');
        $this->newLine();

        $this->components->twoColumnDetail('URL', config('app.url'));
        $this->components->twoColumnDetail('Admin Email', $adminEmail);
        $this->components->twoColumnDetail('Currency', $currency);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $arguments */
    private function runRequired(string $command, array $arguments = []): void
    {
        if ($this->callSilently($command, $arguments) !== self::SUCCESS) {
            throw new \RuntimeException("Installation stopped: {$command} failed.");
        }
    }

    /** @param list<string|\Illuminate\Contracts\Validation\Rule|ValidationRule|\Stringable> $rules */
    private function askValid(string $question, ?string $default, array $rules): string
    {
        while (true) {
            $value = $this->ask($question, $default);
            $validator = Validator::make(['input' => $value], ['input' => $rules]);

            if ($validator->passes()) {
                return $value;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }
        }
    }

    /** @param list<string|\Illuminate\Contracts\Validation\Rule|ValidationRule|\Stringable> $rules */
    private function secretValid(string $question, array $rules): string
    {
        while (true) {
            $value = $this->secret($question);

            if ($value === null || $value === '') {
                $this->components->error('A password is required.');

                continue;
            }

            $validator = Validator::make(['input' => $value], ['input' => $rules]);

            if ($validator->passes()) {
                return $value;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }
        }
    }
}
