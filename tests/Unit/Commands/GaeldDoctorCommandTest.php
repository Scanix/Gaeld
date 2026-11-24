<?php

namespace Tests\Unit\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaeldDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_key_requires_recovery_instead_of_replacement(): void
    {
        config()->set('app.key', '');

        $this->artisan('gaeld:doctor')
            ->assertFailed()
            ->expectsOutputToContain('Restore the original key');
    }

    public function test_runs_successfully_with_healthy_default_config(): void
    {
        // phpunit.xml sets TRUSTED_PROXIES=* globally for the test suite (other
        // feature tests rely on trusted X-Forwarded-* headers); reset it here to
        // represent a genuinely clean self-hosted default.
        config()->set([
            'app.url' => 'http://localhost:8080',
            'proxy.trusted_proxies' => null,
            'proxy.force_https' => null,
            'session.domain' => null,
            'session.secure' => false,
        ]);

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('All checks passed');
    }

    public function test_legacy_native_launcher_cannot_reopen_an_installation_without_a_private_socket(): void
    {
        if (file_exists('/run/gaeld-fpm.sock')) {
            $this->markTestSkipped('This machine already has a managed native PHP-FPM socket.');
        }

        $files = new Filesystem;
        $original = base_path();
        $root = sys_get_temp_dir().'/gaeld-native-doctor-'.bin2hex(random_bytes(8));
        $release = $root.'/releases/fixture';

        try {
            $files->ensureDirectoryExists($root.'/.gaeld', 0700);
            $files->ensureDirectoryExists($root.'/shared', 0700);
            $files->ensureDirectoryExists($release, 0700);
            $files->put($root.'/shared/.env', '');
            symlink($root.'/shared/.env', $release.'/.env');
            symlink($release, $root.'/current');
            $this->app->setBasePath($release);
            config()->set('distribution.managed', false);

            // A source installation can use shared environment files and a current symlink too.
            $this->artisan('gaeld:doctor')
                ->doesntExpectOutputToContain('Use the corrected managed launcher');

            $files->put($release.'/release.json', '{}');
            $this->artisan('gaeld:doctor')
                ->assertFailed()
                ->expectsOutputToContain('Use the corrected managed launcher');
        } finally {
            $this->app->setBasePath($original);
            $files->deleteDirectory($root);
        }
    }

    public function test_warns_when_https_app_url_has_no_trusted_proxies(): void
    {
        config()->set('app.url', 'https://gaeld.example.ch');
        config()->set('proxy.trusted_proxies', null);

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('TRUSTED_PROXIES');
    }

    public function test_warns_when_trusted_proxies_is_wildcard(): void
    {
        config()->set('proxy.trusted_proxies', '*');

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('trusts ANY upstream');
    }

    public function test_warns_when_secure_cookie_set_without_https(): void
    {
        config()->set('app.url', 'http://localhost:8080');
        config()->set('session.secure', true);

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('SESSION_SECURE_COOKIE=true');
    }

    public function test_warns_when_session_domain_does_not_match_app_url_host(): void
    {
        config()->set('app.url', 'https://gaeld.example.ch');
        config()->set('session.domain', 'other-domain.test');

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('SESSION_DOMAIN');
    }

    public function test_fails_when_app_debug_enabled_in_production(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);

        $this->artisan('gaeld:doctor')
            ->assertFailed()
            ->expectsOutputToContain('APP_DEBUG=true');
    }
}
