<?php

namespace Tests\Traits;

use App\Domains\Organizations\Models\Organization;
use App\Support\FeatureFlag;

/**
 * Provides a helper to create an active subscription for an organization
 * when running in SaaS mode. This prevents the EnsureActiveSubscription
 * middleware from redirecting to the billing page during tests.
 */
trait WithActiveSubscription
{
    protected function ensureSubscriptionIfSaas(Organization $org): void
    {
        if (! FeatureFlag::isSaas()) {
            return;
        }

        if (! class_exists(\Plugins\GaeldEE\Domains\Billing\Models\Plan::class)) {
            return;
        }

        $plan = \Plugins\GaeldEE\Domains\Billing\Models\Plan::where('slug', 'business')->first();

        if (! $plan) {
            return;
        }

        \Plugins\GaeldEE\Domains\Billing\Models\Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }
}
