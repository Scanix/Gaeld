<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Policies\OrganizationPolicy;
use App\Domains\Organizations\Services\InvitationService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Plugins\GaeldEE\Domains\Billing\Models\Plan;
use Plugins\GaeldEE\Domains\Billing\Models\Subscription;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class MemberQuotaTest extends TestCase
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

        config()->set('features.saas', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
    }

    public function test_cloud_free_allows_one_owner_but_no_second_member_or_organization(): void
    {
        [$owner, $organization] = $this->organizationWithPlan(Plan::cloudFree());

        $this->assertFalse(app(InvitationService::class)->canAddMember($organization));
        $this->assertFalse((new OrganizationPolicy)->create($owner));
    }

    public function test_paid_plans_allow_their_configured_member_capacity(): void
    {
        [$owner, $organization] = $this->organizationWithPlan(Plan::solo());

        $this->assertTrue(app(InvitationService::class)->canAddMember($organization));

        Subscription::query()->where('organization_id', $organization->id)->update(['plan_id' => Plan::team()->id]);

        $this->assertTrue(app(InvitationService::class)->canAddMember($organization));
        $this->assertFalse((new OrganizationPolicy)->create($owner));
    }

    public function test_billing_page_exposes_members_and_pending_invitations_as_consumption(): void
    {
        [$owner, $organization] = $this->organizationWithPlan(Plan::solo());

        OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => 'pending@example.test',
            'role' => 'member',
            'token' => hash('sha256', 'pending-token'),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_organization_id' => $organization->id])
            ->get(route('billing.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Plans')
                ->where('auth.member_quota.members', 1)
                ->where('auth.member_quota.pending_invitations', 1)
                ->where('auth.member_quota.total', 2)
                ->where('auth.member_quota.member_limit', 3));
    }

    /** @return array{0: User, 1: Organization} */
    private function organizationWithPlan(Plan $plan): array
    {
        $this->seedPermissions();
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $organization = Organization::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($owner, $organization, 'owner');
        $owner->switchOrganization($organization);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        return [$owner, $organization];
    }
}
