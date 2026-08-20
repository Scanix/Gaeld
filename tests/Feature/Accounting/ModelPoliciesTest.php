<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\TaxDeclaration;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class ModelPoliciesTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private User $viewer;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->viewer = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Policy Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->organization->users()->attach($this->viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($this->viewer, $this->organization, 'viewer');

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function asViewer(): self
    {
        return $this->actingAs($this->viewer)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    // ─── CostCenter ──────────────────────────────────────────────────────────

    public function test_viewer_cannot_update_cost_center(): void
    {
        $costCenter = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC01',
            'name' => 'Marketing',
        ]);

        $this->asViewer()
            ->put("/accounting/cost-centers/{$costCenter->id}", [
                'code' => 'CC01',
                'name' => 'Marketing Updated',
            ])
            ->assertForbidden();
    }

    public function test_viewer_cannot_delete_cost_center(): void
    {
        $costCenter = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC02',
            'name' => 'Sales',
        ]);

        $this->asViewer()
            ->delete("/accounting/cost-centers/{$costCenter->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_update_other_org_cost_center(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'CHF']);
        $costCenter = CostCenter::create([
            'organization_id' => $otherOrg->id,
            'code' => 'CC99',
            'name' => 'Other',
        ]);

        $this->asOwner()
            ->put("/accounting/cost-centers/{$costCenter->id}", [
                'code' => 'CC99',
                'name' => 'Hacked',
            ])
            ->assertNotFound();
    }

    // ─── ExchangeRate ─────────────────────────────────────────────────────────

    public function test_viewer_cannot_delete_exchange_rate(): void
    {
        $rate = ExchangeRate::create([
            'organization_id' => $this->organization->id,
            'currency_from' => 'EUR',
            'currency_to' => 'CHF',
            'rate' => '1.05',
            'date' => '2024-01-01',
            'source' => 'manual',
        ]);

        $this->asViewer()
            ->delete("/accounting/exchange-rates/{$rate->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_delete_other_org_exchange_rate(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org 2', 'currency' => 'CHF']);
        $rate = ExchangeRate::create([
            'organization_id' => $otherOrg->id,
            'currency_from' => 'EUR',
            'currency_to' => 'CHF',
            'rate' => '1.05',
            'date' => '2024-01-01',
            'source' => 'manual',
        ]);

        $this->asOwner()
            ->delete("/accounting/exchange-rates/{$rate->id}")
            ->assertNotFound();
    }

    // ─── TaxDeclaration ───────────────────────────────────────────────────────

    public function test_viewer_cannot_view_tax_declaration(): void
    {
        $declaration = TaxDeclaration::create([
            'organization_id' => $this->organization->id,
            'fiscal_year' => 2024,
            'canton' => 'ZH',
            'status' => 'draft',
        ]);

        $this->asViewer()
            ->get("/accounting/tax-declarations/{$declaration->id}")
            ->assertForbidden();
    }

    public function test_viewer_cannot_finalize_tax_declaration(): void
    {
        $declaration = TaxDeclaration::create([
            'organization_id' => $this->organization->id,
            'fiscal_year' => 2024,
            'canton' => 'ZH',
            'status' => 'draft',
        ]);

        $this->asViewer()
            ->post("/accounting/tax-declarations/{$declaration->id}/finalize")
            ->assertForbidden();
    }

    public function test_user_cannot_view_other_org_tax_declaration(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org 3', 'currency' => 'CHF']);
        $declaration = TaxDeclaration::create([
            'organization_id' => $otherOrg->id,
            'fiscal_year' => 2024,
            'canton' => 'BE',
            'status' => 'draft',
        ]);

        $this->asOwner()
            ->get("/accounting/tax-declarations/{$declaration->id}")
            ->assertNotFound();
    }

    // ─── ConsolidationGroup ───────────────────────────────────────────────────

    public function test_viewer_cannot_view_consolidation_report(): void
    {
        $group = ConsolidationGroup::create([
            'organization_id' => $this->organization->id,
            'name' => 'Group A',
            'member_organization_ids' => [],
            'base_currency' => 'CHF',
        ]);

        $this->asViewer()
            ->get("/accounting/consolidation/{$group->id}/report")
            ->assertForbidden();
    }

    public function test_viewer_cannot_store_elimination(): void
    {
        $group = ConsolidationGroup::create([
            'organization_id' => $this->organization->id,
            'name' => 'Group B',
            'member_organization_ids' => [],
            'base_currency' => 'CHF',
        ]);

        $this->asViewer()
            ->post("/accounting/consolidation/{$group->id}/eliminations", [
                'fiscal_year' => 2024,
                'amount' => 1000,
                'currency' => 'CHF',
                'description' => 'Test',
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_view_other_org_consolidation_report(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org 4', 'currency' => 'CHF']);
        $group = ConsolidationGroup::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Group',
            'member_organization_ids' => [],
            'base_currency' => 'CHF',
        ]);

        $this->asOwner()
            ->get("/accounting/consolidation/{$group->id}/report")
            ->assertNotFound();
    }
}
