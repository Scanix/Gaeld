<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Budget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class BudgetFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->revenueAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $this->expenseAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '6000',
            'name' => 'Office Expenses',
            'type' => AccountType::Expense->value,
        ]);
    }

    public function test_budget_index_page_renders(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get(route('accounting.budgets'));

        $response->assertStatus(200);
    }

    public function test_budget_index_returns_budgets_for_year(): void
    {
        Budget::create([
            'organization_id' => $this->org->id,
            'account_id' => $this->revenueAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '5000.00',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get(route('accounting.budgets', ['year' => 2026]));

        $response->assertStatus(200);
    }

    public function test_can_store_budget(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('accounting.budgets.store'), [
                'account_id' => $this->revenueAccount->id,
                'fiscal_year' => 2026,
                'monthly_amount' => '8500.00',
            ]);

        $response->assertRedirect(route('accounting.budgets', ['year' => 2026]));

        $this->assertDatabaseHas('budgets', [
            'organization_id' => $this->org->id,
            'account_id' => $this->revenueAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '8500.00',
        ]);
    }

    public function test_store_budget_uses_upsert(): void
    {
        Budget::create([
            'organization_id' => $this->org->id,
            'account_id' => $this->expenseAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '1000.00',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('accounting.budgets.store'), [
                'account_id' => $this->expenseAccount->id,
                'fiscal_year' => 2026,
                'monthly_amount' => '1500.00',
            ]);

        $response->assertRedirect();

        // Should update — not create a second row
        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', [
            'organization_id' => $this->org->id,
            'account_id' => $this->expenseAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '1500.00',
        ]);
    }

    public function test_store_budget_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('accounting.budgets.store'), []);

        $response->assertSessionHasErrors(['account_id', 'fiscal_year', 'monthly_amount']);
    }

    public function test_can_delete_budget(): void
    {
        $budget = Budget::create([
            'organization_id' => $this->org->id,
            'account_id' => $this->revenueAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '5000.00',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->delete(route('accounting.budgets.destroy', $budget));

        $response->assertRedirect(route('accounting.budgets', ['year' => 2026]));
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_budget_requires_authentication(): void
    {
        $response = $this->get(route('accounting.budgets'));

        $response->assertRedirect();
    }

    public function test_budget_scope_for_year(): void
    {
        Budget::create([
            'organization_id' => $this->org->id,
            'account_id' => $this->revenueAccount->id,
            'fiscal_year' => 2025,
            'monthly_amount' => '3000.00',
        ]);

        Budget::create([
            'organization_id' => $this->org->id,
            'account_id' => $this->revenueAccount->id,
            'fiscal_year' => 2026,
            'monthly_amount' => '5000.00',
        ]);

        $budgets2026 = Budget::forYear(2026)->get();
        $this->assertCount(1, $budgets2026);
        $this->assertEquals(2026, $budgets2026->first()->fiscal_year);
    }
}
