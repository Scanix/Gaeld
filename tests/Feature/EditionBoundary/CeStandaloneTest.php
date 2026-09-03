<?php

namespace Tests\Feature\EditionBoundary;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CeStandaloneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('plugins.enabled', false);
        config()->set('features.saas', false);
    }

    public function test_ce_boots_without_ee_bindings_or_private_routes(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));

        $this->assertFalse($this->app->bound('Plugins\\GaeldEE\\Domains\\Billing\\Services\\BillingService'));
        $this->assertFalse(Route::has('billing.index'));
        $this->assertFalse(Route::has('saas-admin.index'));
        $this->assertFalse(Route::has('stripe.webhook'));
    }

    public function test_core_ce_routes_remain_available_when_ee_is_absent(): void
    {
        $this->assertTrue(Route::has('dashboard'));
        $this->assertTrue(Route::has('invoices.index'));
        $this->assertTrue(Route::has('expenses.index'));
        $this->assertTrue(Route::has('accounting.chart'));
        $this->assertTrue(Route::has('reports.pnl'));
    }
}
