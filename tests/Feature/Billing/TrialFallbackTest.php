<?php

namespace Tests\Feature\Billing;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Plugins\GaeldEE\Domains\Billing\Actions\FallbackExpiredPaidTrialAction;
use Plugins\GaeldEE\Domains\Billing\Models\Plan;
use Plugins\GaeldEE\Domains\Billing\Models\Subscription;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class TrialFallbackTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    public function createApplication(): Application
    {
        $_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../../..');
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

        config()->set('features.saas', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
    }

    public function test_expired_team_trial_returns_to_cloud_free_without_stripe_data(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::team()->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->subMinute(),
        ]);

        $this->assertTrue(app(FallbackExpiredPaidTrialAction::class)->handle($subscription));

        $subscription->refresh();
        $this->assertSame(Plan::cloudFree()->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNull($subscription->stripe_subscription_id);
    }

    public function test_fallback_is_idempotent_and_does_not_replace_a_converted_trial(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::team()->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->subMinute(),
            'stripe_subscription_id' => 'sub_converted_team_trial',
        ]);

        $action = app(FallbackExpiredPaidTrialAction::class);

        $this->assertFalse($action->handle($subscription));
        $subscription->refresh();
        $this->assertSame(Plan::team()->id, $subscription->plan_id);
        $this->assertSame('trialing', $subscription->status);
    }

    public function test_expired_solo_trial_also_returns_to_cloud_free(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => Plan::solo()->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->subMinute(),
        ]);

        $this->assertTrue(app(FallbackExpiredPaidTrialAction::class)->handle($subscription));

        $subscription->refresh();
        $this->assertSame(Plan::cloudFree()->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }
}
