<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GaeldDemoCommand extends Command
{
    protected $signature = 'gaeld:demo
        {--fresh : Wipe the database before seeding demo data}';

    protected $description = 'Seed Gäld with realistic demo data for testing and demonstrations';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (! $this->confirm('This will wipe ALL data. Continue?')) {
                return self::FAILURE;
            }

            $this->components->task('Resetting database', function () {
                $this->callSilently('migrate:fresh', ['--force' => true]);
            });

            $this->components->task('Seeding chart of accounts', function () {
                $this->callSilently('db:seed', [
                    '--class' => 'Database\\Seeders\\SwissChartOfAccountsSeeder',
                    '--force' => true,
                ]);
            });

            $this->components->task('Seeding VAT rates', function () {
                $this->callSilently('db:seed', [
                    '--class' => 'Database\\Seeders\\SwissVatRatesSeeder',
                    '--force' => true,
                ]);
            });
        }

        $this->components->info('Seeding demo data…');

        $this->components->task('Creating demo users, invoices, expenses, and bank transactions', function () {
            $this->callSilently('db:seed', [
                '--class' => 'Database\\Seeders\\DemoDataSeeder',
                '--force' => true,
            ]);
        });

        $this->newLine();
        $this->components->info('Demo data seeded successfully!');
        $this->components->twoColumnDetail('Login', 'admin@gaeld.local');
        $this->components->twoColumnDetail('Password', 'password');

        return self::SUCCESS;
    }
}
