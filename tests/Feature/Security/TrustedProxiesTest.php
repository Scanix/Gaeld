<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    /**
     * Regression test for issue #18: when running behind a TLS-terminating
     * reverse proxy (Coolify/Traefik/nginx/...), Laravel must honour the
     * X-Forwarded-Proto header so generated URLs and redirects use https://.
     */
    public function test_https_scheme_is_detected_from_x_forwarded_proto_header(): void
    {
        Route::get('/__trusted-proxies-test', fn () => [
            'scheme' => request()->getScheme(),
            'is_secure' => request()->isSecure(),
            'url' => url('/login'),
        ]);

        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'accounting.example.com',
        ])->getJson('/__trusted-proxies-test');

        $response->assertOk();
        $response->assertJson([
            'scheme' => 'https',
            'is_secure' => true,
        ]);
        $this->assertStringStartsWith('https://', $response->json('url'));
    }
}
