<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaeldDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_successfully_with_healthy_default_config(): void
    {
        // phpunit.xml sets TRUSTED_PROXIES=* globally for the test suite (other
        // feature tests rely on trusted X-Forwarded-* headers); reset it here to
        // represent a genuinely clean self-hosted default.
        config()->set('proxy.trusted_proxies', null);

        $this->artisan('gaeld:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('All checks passed');
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
