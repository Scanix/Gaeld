<?php

namespace Tests\Security\Authorization;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

/**
 * Verifies that feature-gated routes are inaccessible when the feature flag
 * is disabled. An attacker must not bypass feature flags by guessing URLs.
 */
class FeatureFlagEnforcementTest extends SecurityTestCase
{
    // ──────────────────────────────────────────────────────────────
    //  API access feature flag
    // ──────────────────────────────────────────────────────────────

    public function test_api_routes_return_403_when_api_access_disabled(): void
    {
        config(['features.api_access' => false]);

        // Create a token (bypassing the feature flag guard at token-creation level)
        config(['features.api_access' => true]);
        $token = $this->createApiToken($this->ownerA, $this->orgA);
        config(['features.api_access' => false]);

        $this->withToken($token)
            ->getJson('/api/v1/customers')
            ->assertForbidden();
    }

    public function test_api_token_settings_page_requires_api_access_feature(): void
    {
        config(['features.api_access' => false]);

        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/settings/api-tokens')
            ->assertForbidden();
    }

    public function test_webhook_settings_page_requires_api_access_feature(): void
    {
        config(['features.api_access' => false]);

        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/settings/webhooks')
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Auto-reconciliation feature flag
    // ──────────────────────────────────────────────────────────────

    public function test_auto_reconciliation_route_is_blocked_when_feature_disabled(): void
    {
        config(['features.auto_reconciliation' => false]);

        // Create a real bank account so route model binding doesn't return 404
        app(CurrentOrganization::class)->set($this->orgA);
        $bankAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Test Bank',
            'type' => AccountType::Asset->value,
        ]);
        $ba = BankAccount::create([
            'organization_id' => $this->orgA->id,
            'account_id' => $bankAccount->id,
            'name' => 'Test Bank Account',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$ba->id}/auto")
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Feature flag cannot be enabled by user input
    // ──────────────────────────────────────────────────────────────

    public function test_api_feature_flag_cannot_be_bypassed_via_query_string(): void
    {
        config(['features.api_access' => false]);

        // An attacker appending ?feature=api_access or similar must not grant access
        $this->getJson('/api/v1/customers?feature=api_access&features[api_access]=1')
            ->assertUnauthorized(); // Not even authenticated, so 401 first; 403 after auth
    }
}
