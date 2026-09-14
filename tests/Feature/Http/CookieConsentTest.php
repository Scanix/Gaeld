<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Vite::useBuildDirectory('build');
    }

    public function test_cookie_consent_is_not_loaded_without_analytics(): void
    {
        config(['services.google.gtm_id' => null]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('cookieConsent');
        $response->assertDontSee('googletagmanager.com');
    }

    public function test_cookie_consent_is_loaded_when_analytics_is_configured(): void
    {
        config(['services.google.gtm_id' => 'GTM-TEST123']);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('cookieConsent');
        $response->assertSee('googletagmanager.com/gtm.js?id=GTM-TEST123', false);
    }
}
