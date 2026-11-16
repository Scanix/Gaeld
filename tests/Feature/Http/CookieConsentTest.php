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
        config(['services.google.ads_id' => null]);

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
        $response->assertDontSee('googletagmanager.com/gtm.js', false);
        $response->assertSee('gaeld-google-config', false);
        $response->assertSee('consentRevision', false);
    }

    public function test_ads_configuration_enables_preferences_without_loading_google_on_application_pages(): void
    {
        config(['services.google.gtm_id' => null, 'services.google.ads_id' => 'AW-123456']);

        $response = $this->get('/login');

        $response->assertOk()->assertSee('cookieConsent')->assertSee('gaeld-google-config', false);
        $response->assertDontSee('googletagmanager.com/gtag/js', false);
        $response->assertDontSee('googleadservices.com', false);
    }
}
