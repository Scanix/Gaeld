<?php

namespace Tests\Feature\SaasAdmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeInertiaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ce_shared_inertia_request_does_not_resolve_ee_support_services(): void
    {
        config()->set('features.saas', false);
        config()->set('plugins.enabled', false);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
        $this->assertFalse($this->app->bound('Plugins\\GaeldEE\\Domains\\SaasAdmin\\Services\\SupportSessionService'));
    }

    public function test_core_frontend_does_not_contain_saas_admin_page_or_component_sources(): void
    {
        $this->assertDirectoryDoesNotExist(resource_path('js/Pages/SaasAdmin'));
        $this->assertDirectoryDoesNotExist(resource_path('js/Components/SaasAdmin'));
    }
}
