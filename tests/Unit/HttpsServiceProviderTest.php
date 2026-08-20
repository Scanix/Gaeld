<?php

namespace Tests\Unit;

use App\Providers\HttpsServiceProvider;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Exercises {@see HttpsServiceProvider} against every FORCE_HTTPS × APP_URL
 * combination that self-hosters can produce. Ensures the auto-detection and
 * explicit override are both correct and that the string values "false" / "0"
 * are treated as false (a common env() gotcha).
 */
class HttpsServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the forced scheme between tests so cases don't leak into each other.
        $reflection = new ReflectionClass(UrlGenerator::class);
        $prop = $reflection->getProperty('forceScheme');
        $prop->setAccessible(true);
        $prop->setValue($this->app['url'], null);

        parent::tearDown();
    }

    public static function forceHttpsMatrix(): array
    {
        return [
            'explicit true forces https' => ['true', 'http://localhost', true],
            'explicit "1" forces https' => ['1', 'http://localhost', true],
            'explicit false disables even with https APP_URL' => ['false', 'https://gaeld.example.ch', false],
            'explicit "0" disables even with https APP_URL' => ['0', 'https://gaeld.example.ch', false],
            'unset with https APP_URL auto-enables' => [null, 'https://gaeld.example.ch', true],
            'unset with http APP_URL stays disabled' => [null, 'http://localhost:8080', false],
            'empty string with https APP_URL auto-enables' => ['', 'https://gaeld.example.ch', true],
        ];
    }

    #[DataProvider('forceHttpsMatrix')]
    public function test_force_https_matrix(?string $forceHttps, string $appUrl, bool $expectHttps): void
    {
        config()->set('proxy.force_https', $forceHttps);
        config()->set('app.url', $appUrl);

        // Re-run the provider's boot() to apply the new config.
        (new HttpsServiceProvider($this->app))->boot();

        $scheme = parse_url(URL::to('/dashboard'), PHP_URL_SCHEME);

        $this->assertSame(
            $expectHttps ? 'https' : 'http',
            $scheme,
            'URL scheme should be '.($expectHttps ? 'https' : 'http').' for FORCE_HTTPS='.var_export($forceHttps, true)." APP_URL={$appUrl}",
        );
    }
}
