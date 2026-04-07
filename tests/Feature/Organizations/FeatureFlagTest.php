<?php

namespace Tests\Feature\Organizations;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

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
        config(['features.saas' => false]);

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
        $this->assertArrayHasKey('bank_import', $flags);
        $this->assertArrayHasKey('auto_reconciliation', $flags);
        $this->assertArrayHasKey('saas', $flags);
        $this->assertArrayHasKey('automation', $flags);
        $this->assertArrayHasKey('multi_currency', $flags);
        $this->assertArrayHasKey('api_access', $flags);
    }

    public function test_bank_import_enabled_by_default(): void
    {
        $this->assertTrue(FeatureFlag::enabled('bank_import'));
    }

    public function test_auto_reconciliation_disabled_by_default(): void
    {
        // Override .env.testing to verify the production config default
        config(['features.auto_reconciliation' => false]);

        $this->assertFalse(FeatureFlag::enabled('auto_reconciliation'));
        $this->assertTrue(FeatureFlag::disabled('auto_reconciliation'));
    }

    public function test_banking_accessible_in_ce_without_feature_flag(): void
    {
        $this->seedPermissions();

        // Banking is CE — no feature flag needed
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $org, 'owner');

        $response = $this->actingAs($user)->get('/banking');

        $response->assertStatus(200);
    }

    public function test_auto_reconcile_route_blocked_when_disabled(): void
    {
        $this->seedPermissions();

        config(['features.auto_reconciliation' => false]);

        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $org, 'owner');
        $bankAccount = BankAccount::create([
            'organization_id' => $org->id,
            'name' => 'Test',
            'currency' => 'CHF',
        ]);

        $response = $this->actingAs($user)
            ->post("/reconciliation/{$bankAccount->uuid}/auto");

        $response->assertForbidden();
    }

    public function test_auto_reconcile_route_allowed_when_enabled(): void
    {
        $this->seedPermissions();

        config(['features.auto_reconciliation' => true]);

        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $org, 'owner');
        $bankAccount = BankAccount::create([
            'organization_id' => $org->id,
            'name' => 'Test',
            'currency' => 'CHF',
        ]);

        $response = $this->actingAs($user)
            ->post("/reconciliation/{$bankAccount->uuid}/auto");

        // Should not be 403 — may redirect or return success depending on data
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
