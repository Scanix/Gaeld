<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class AddSecurityHeadersTest extends TestCase
{
    public function test_csp_frame_src_allows_same_origin_and_required_external_domains(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString(
            "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://docs.gaeld.ch",
            $csp,
        );
    }
}
