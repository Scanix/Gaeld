<?php

namespace Tests\Security\EditionBoundary;

use Tests\TestCase;

class CeFailClosedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('plugins.enabled', false);
        config()->set('features.saas', false);
    }

    public function test_commercial_endpoints_are_unavailable_without_ee(): void
    {
        foreach (['/billing', '/saas-admin', '/stripe/webhook'] as $path) {
            $response = $this->get($path);

            $response->assertNotFound();
            $content = $response->getContent();
            $this->assertIsString($content);
            $this->assertStringNotContainsString('Plugins\\GaeldEE', $content);
        }
    }
}
