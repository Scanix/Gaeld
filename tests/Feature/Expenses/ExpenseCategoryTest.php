<?php

namespace Tests\Feature\Expenses;

use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_it_creates_an_expense_category(): void
    {
        $response = $this->actAsOrg()->post('/settings/expense-categories', [
            'name' => 'Software',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expense_categories', [
            'organization_id' => $this->org->id,
            'name' => 'Software',
        ]);
    }

    public function test_it_deletes_an_expense_category(): void
    {
        $category = ExpenseCategory::create([
            'organization_id' => $this->org->id,
            'name' => 'Software',
            'sort_order' => 1,
        ]);

        $response = $this->actAsOrg()->delete("/settings/expense-categories/{$category->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_it_prevents_deleting_a_category_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignCategory = ExpenseCategory::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other org category',
            'sort_order' => 1,
        ]);

        $response = $this->actAsOrg()->delete("/settings/expense-categories/{$foreignCategory->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('expense_categories', ['id' => $foreignCategory->id]);
    }

    public function test_index_is_authorized_and_scoped_to_the_current_organization(): void
    {
        ExpenseCategory::create([
            'organization_id' => $this->org->id,
            'name' => 'My org category',
            'sort_order' => 1,
        ]);

        $otherOrg = Organization::factory()->create();
        ExpenseCategory::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other org category',
            'sort_order' => 1,
        ]);

        $response = $this->actAsOrg()->get('/settings/expense-categories');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('My org category'));
        $this->assertFalse($names->contains('Other org category'));
    }
}
