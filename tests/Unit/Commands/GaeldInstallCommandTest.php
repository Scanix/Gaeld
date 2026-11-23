<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\GaeldInstallCommand;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GaeldInstallCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        // Config caching boots a new application connection; installation tests cannot use an outer transaction.
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('db:wipe', ['--force' => true])->run();
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function tearDown(): void
    {
        @unlink(app()->getCachedConfigPath());
        @unlink(app()->getCachedRoutesPath());

        parent::tearDown();
    }

    public function test_unattended_installation_requires_operator_supplied_credentials(): void
    {
        $this->artisan('gaeld:install', ['--no-interaction' => true])
            ->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_secret_file_creates_named_administrator_without_printing_password(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gaeld-admin-');
        $password = 'OnlyForThisTest42!';
        file_put_contents($path, json_encode([
            'name' => 'Monika', 'email' => 'admin@example.test', 'password' => $password,
            'organization' => 'Example Holding AG', 'currency' => 'CHF', 'locale' => 'en',
        ], JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        try {
            $this->assertSame(0, Organization::query()->count());
            $exit = Artisan::call('gaeld:install', ['--no-interaction' => true, '--admin-secret-file' => $path]);
            $output = Artisan::output();
            $this->assertSame(0, $exit, $output);
            $this->assertStringNotContainsString($password, $output);
            $this->assertSame(1, User::query()->count(), $output);
            $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertTrue($user->hasVerifiedEmail());
            $this->assertSame('Example Holding AG', Organization::query()->firstOrFail()->name);
        } finally {
            unlink($path);
        }
    }

    public function test_it_returns_success_without_creating_a_second_installation(): void
    {
        Organization::create([
            'name' => 'Existing Org',
            'legal_name' => 'Existing Org AG',
            'currency' => 'CHF',
            'locale' => 'en',
            'country' => 'CH',
        ]);

        $this->artisan('gaeld:install', ['--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_it_exposes_expected_command_metadata(): void
    {
        $command = app(GaeldInstallCommand::class);

        $this->assertSame('gaeld:install', $command->getName());
        $this->assertStringContainsString('Install Gäld', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('demo'));
    }

    public function test_it_never_replaces_a_missing_key_on_an_existing_database(): void
    {
        config()->set('app.key', '');
        $before = file_get_contents(base_path('.env'));

        $this->artisan('gaeld:install', ['--no-interaction' => true])->assertFailed();

        $this->assertSame($before, file_get_contents(base_path('.env')));
    }
}
