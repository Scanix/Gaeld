<?php

namespace Tests\Feature;

use App\Services\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_defaults_to_disabled(): void
    {
        $this->assertFalse(FeatureFlag::enabled('bank_sync'));
        $this->assertTrue(FeatureFlag::disabled('bank_sync'));
    }

    public function test_feature_flag_can_be_enabled(): void
    {
        config(['features.bank_sync' => true]);

        $this->assertTrue(FeatureFlag::enabled('bank_sync'));
        $this->assertFalse(FeatureFlag::disabled('bank_sync'));
    }

    public function test_community_edition_by_default(): void
    {
        $this->assertTrue(FeatureFlag::isCommunity());
        $this->assertFalse(FeatureFlag::isSaas());
    }

    public function test_saas_edition_when_enabled(): void
    {
        config(['features.saas' => true]);

        $this->assertTrue(FeatureFlag::isSaas());
        $this->assertFalse(FeatureFlag::isCommunity());
    }

    public function test_all_returns_all_flags(): void
    {
        $flags = FeatureFlag::all();

        $this->assertArrayHasKey('bank_sync', $flags);
        $this->assertArrayHasKey('saas', $flags);
        $this->assertArrayHasKey('automation', $flags);
        $this->assertArrayHasKey('multi_currency', $flags);
        $this->assertArrayHasKey('api_access', $flags);
    }

    public function test_feature_middleware_blocks_disabled_feature(): void
    {
        config(['features.bank_sync' => false]);

        $user = \App\Domains\Users\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/banking');

        $response->assertForbidden();
    }

    public function test_feature_middleware_allows_enabled_feature(): void
    {
        config(['features.bank_sync' => true]);

        $user = \App\Domains\Users\Models\User::factory()->create();
        $org = \App\Domains\Organizations\Models\Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get('/banking');

        // Should not be 403 when enabled (may be 200 or redirect depending on view setup)
        $response->assertStatus(200);
    }
}
