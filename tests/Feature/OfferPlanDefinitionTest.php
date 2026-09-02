<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Plugins\GaeldEE\Domains\Billing\Models\Plan;
use Tests\TestCase;

class OfferPlanDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../..');
        $_ENV['PLUGINS_ENABLED'] = 'true';
        RefreshDatabaseState::$migrated = false;
        $app = parent::createApplication();
        $_ENV['PLUGINS_ENABLED'] = 'false';

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Plan::class)) {
            $this->markTestSkipped('Enterprise Edition is not enabled.');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
    }

    public function test_canonical_offer_records_match_the_approved_contract(): void
    {
        $cloudFree = Plan::cloudFree();
        $solo = Plan::solo();
        $team = Plan::team();

        $this->assertSame('Cloud Free', $cloudFree->name);
        $this->assertSame(1, $cloudFree->max_users);
        $this->assertSame(1, $cloudFree->max_organizations);
        $this->assertSame(5, $cloudFree->max_invoices_per_month);
        $this->assertSame(-1, $cloudFree->max_ocr_scans_per_day);
        $this->assertSame(5, $cloudFree->max_ocr_scans_per_month);
        $this->assertSame(262_144_000, $cloudFree->max_storage_bytes);
        $this->assertTrue($cloudFree->isPublic());

        $this->assertSame(15.0, $solo->price_chf);
        $this->assertSame(3, $solo->max_users);
        $this->assertTrue($solo->isPublic());

        $this->assertSame(39.0, $team->price_chf);
        $this->assertSame(5, $team->max_users);
        $this->assertTrue($team->isPublic());
        $this->assertTrue($team->hasFeature('api_access'));
    }

    public function test_legacy_paid_plans_remain_persisted_but_are_not_available_for_signup(): void
    {
        $starter = Plan::where('slug', 'starter')->firstOrFail();
        $business = Plan::where('slug', 'business')->firstOrFail();

        $this->assertTrue($starter->isLegacy());
        $this->assertTrue($business->isLegacy());
        $this->assertFalse($starter->isPublic());
        $this->assertFalse($business->isPublic());
        $this->assertSame(3, Plan::availableForSignup()->count());
        $this->assertDatabaseHas('ee_plans', ['slug' => 'starter']);
        $this->assertDatabaseHas('ee_plans', ['slug' => 'business']);
    }
}
