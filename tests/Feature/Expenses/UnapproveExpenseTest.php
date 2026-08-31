<?php

namespace Tests\Feature\Expenses;

use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\UnapproveExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class UnapproveExpenseTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function createExpense(array $overrides = []): Expense
    {
        $action = new CreateExpenseAction;

        return $action->execute(CreateExpenseData::fromArray(array_merge([
            'organization_id' => $this->org->id,
            'category' => 'Software',
            'description' => 'Adobe Creative Cloud',
            'amount' => 700.00,
            'date' => '2026-03-16',
            'vendor' => 'Adobe Inc.',
        ], $overrides)));
    }

    public function test_it_reverts_an_approved_expense_to_pending(): void
    {
        $expense = $this->createExpense();
        $expense = app(ApproveExpenseAction::class)->execute($expense);
        $this->assertEquals(ExpenseStatus::Approved, $expense->status);

        $expense = app(UnapproveExpenseAction::class)->execute($expense);

        $this->assertEquals(ExpenseStatus::Pending, $expense->status);
    }

    public function test_it_refuses_to_unapprove_a_pending_expense(): void
    {
        $expense = $this->createExpense();

        $this->expectException(InvalidExpenseStateException::class);
        app(UnapproveExpenseAction::class)->execute($expense);
    }

    public function test_unapprove_route_reverts_the_expense(): void
    {
        $expense = $this->createExpense();
        $expense = app(ApproveExpenseAction::class)->execute($expense);

        $response = $this->actAsOrg()->post("/expenses/{$expense->id}/unapprove");

        $response->assertRedirect(route('expenses.show', $expense));
        $this->assertEquals(ExpenseStatus::Pending, $expense->fresh()->status);
    }

    public function test_an_approved_expense_can_now_be_deleted(): void
    {
        $expense = $this->createExpense();
        $expense = app(ApproveExpenseAction::class)->execute($expense);

        $response = $this->actAsOrg()->delete("/expenses/{$expense->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted($expense);
    }
}
