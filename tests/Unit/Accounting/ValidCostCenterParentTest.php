<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Rules\ValidCostCenterParent;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Translation\PotentiallyTranslatedString;
use Tests\TestCase;

/**
 * ValidCostCenterParent previously had zero test coverage despite containing
 * real cycle-detection logic (walking the parent_id ancestry chain). A bug
 * here (e.g. an off-by-one, or the visited-set check firing too late) could
 * let a user create a circular cost-center hierarchy, which would silently
 * break any report that recursively walks the tree (infinite loop or
 * incorrect roll-ups).
 */
class ValidCostCenterParentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'CC Org', 'currency' => 'CHF']);
        $this->otherOrganization = Organization::create(['name' => 'Other Org', 'currency' => 'CHF']);
    }

    private function fails(ValidCostCenterParent $rule, mixed $value): ?string
    {
        $failed = null;
        $rule->validate('parent_id', $value, function (string|PotentiallyTranslatedString $message) use (&$failed) {
            $failed = (string) $message;
        });

        return $failed;
    }

    public function test_null_or_empty_value_passes(): void
    {
        $rule = new ValidCostCenterParent($this->organization->id);

        $this->assertNull($this->fails($rule, null));
        $this->assertNull($this->fails($rule, ''));
    }

    public function test_non_existent_parent_fails(): void
    {
        $rule = new ValidCostCenterParent($this->organization->id);

        $this->assertNotNull($this->fails($rule, 999999));
    }

    public function test_parent_belonging_to_another_organization_fails(): void
    {
        $foreignParent = CostCenter::create([
            'organization_id' => $this->otherOrganization->id,
            'code' => 'CC-F',
            'name' => 'Foreign',
        ]);

        $rule = new ValidCostCenterParent($this->organization->id);

        $this->assertNotNull($this->fails($rule, $foreignParent->id));
    }

    public function test_valid_parent_in_the_same_organization_passes(): void
    {
        $parent = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-P',
            'name' => 'Parent',
        ]);

        $rule = new ValidCostCenterParent($this->organization->id);

        $this->assertNull($this->fails($rule, $parent->id));
    }

    public function test_a_cost_center_cannot_be_its_own_parent(): void
    {
        $current = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-SELF',
            'name' => 'Self',
        ]);

        $rule = new ValidCostCenterParent($this->organization->id, $current);

        $this->assertNotNull($this->fails($rule, $current->id));
    }

    public function test_direct_circular_hierarchy_is_rejected(): void
    {
        // parent -> child; attempting parent.parent_id = child creates a 2-node cycle.
        $parent = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-PAR',
            'name' => 'Parent',
        ]);
        $child = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-CHI',
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $rule = new ValidCostCenterParent($this->organization->id, $parent);

        $this->assertNotNull($this->fails($rule, $child->id));
    }

    public function test_deep_circular_hierarchy_is_rejected(): void
    {
        // grandparent -> parent -> child; attempting grandparent.parent_id = child
        // must be rejected even though child is not a *direct* child of grandparent.
        $grandparent = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-GP',
            'name' => 'Grandparent',
        ]);
        $parent = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-P2',
            'name' => 'Parent',
            'parent_id' => $grandparent->id,
        ]);
        $child = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-C2',
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $rule = new ValidCostCenterParent($this->organization->id, $grandparent);

        $this->assertNotNull($this->fails($rule, $child->id));
    }

    public function test_unrelated_parent_reassignment_passes(): void
    {
        $current = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-CUR',
            'name' => 'Current',
        ]);
        $unrelated = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'CC-UNR',
            'name' => 'Unrelated',
        ]);

        $rule = new ValidCostCenterParent($this->organization->id, $current);

        $this->assertNull($this->fails($rule, $unrelated->id));
    }
}
