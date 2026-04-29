<?php

namespace Tests\Feature\Expenses;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ExpenseFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '6530',
            'name' => 'Software and Subscriptions',
            'type' => AccountType::Expense->value,
        ]);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
    }

    private function createExpense(array $overrides = []): Expense
    {
        $action = new CreateExpenseAction;

        return $action->execute(CreateExpenseData::fromArray(array_merge([
            'organization_id' => $this->org->id,
            'category' => 'Software and Subscriptions',
            'description' => 'Adobe Creative Cloud',
            'amount' => 700.00,
            'vat_amount' => 56.70,
            'date' => '2026-03-16',
            'vendor' => 'Adobe Inc.',
        ], $overrides)));
    }

    public function test_complete_expense_flow_with_approval(): void
    {
        // 1. Create expense
        $expense = $this->createExpense();
        $this->assertEquals(ExpenseStatus::Pending, $expense->status);
        $this->assertEquals('700.00', $expense->amount);

        // 2. Approve expense
        $approveAction = new ApproveExpenseAction;
        $expense = $approveAction->execute($expense);
        $this->assertEquals(ExpenseStatus::Approved, $expense->status);

        // 3. Post expense to ledger
        $postAction = app(PostExpenseAction::class);
        $expense = $postAction->execute($expense, '6530');

        $this->assertEquals(ExpenseStatus::Posted, $expense->status);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertTrue($expense->journalEntry->isBalanced());

        // Verify ledger entries
        $lines = $expense->journalEntry->lines;
        $this->assertCount(2, $lines);
    }

    public function test_complete_expense_flow(): void
    {
        $vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $expense = $this->createExpense(['vat_rate_id' => $vatRate->id]);

        $this->assertEquals(ExpenseStatus::Pending, $expense->status);

        // Approve expense first (state machine: Pending → Approved → Posted)
        $approveAction = new ApproveExpenseAction;
        $expense = $approveAction->execute($expense);
        $this->assertEquals(ExpenseStatus::Approved, $expense->status);

        // Post expense to ledger
        $postAction = app(PostExpenseAction::class);
        $expense = $postAction->execute($expense, '6530');

        $this->assertEquals(ExpenseStatus::Posted, $expense->status);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertTrue($expense->journalEntry->isBalanced());

        $lines = $expense->journalEntry->lines;
        $this->assertCount(2, $lines);

        $expenseAccountId = Account::where('code', '6530')
            ->where('organization_id', $this->org->id)
            ->value('id');
        $bankAccountId = Account::where('code', '1020')
            ->where('organization_id', $this->org->id)
            ->value('id');

        $debitLine = $lines->firstWhere('account_id', $expenseAccountId);
        $creditLine = $lines->firstWhere('account_id', $bankAccountId);

        $this->assertEquals('700.00', $debitLine->debit);
        $this->assertEquals('700.00', $creditLine->credit);
    }

    public function test_cannot_post_already_posted_expense(): void
    {
        $expense = $this->createExpense([
            'category' => 'Software',
            'amount' => 100.00,
            'vat_amount' => null,
            'vendor' => null,
            'description' => null,
        ]);

        $approveAction = new ApproveExpenseAction;
        $expense = $approveAction->execute($expense);

        $postAction = app(PostExpenseAction::class);
        $postAction->execute($expense, '6530');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already posted');

        $postAction->execute($expense->fresh(), '6530');
    }

    public function test_destroy_uses_generic_flash_error_when_exception_message_is_empty(): void
    {
        $expense = $this->createExpense();

        $this->mock(DeleteExpenseAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new InvalidExpenseStateException(''));
        });

        $response = $this->actAsOrg()
            ->from(route('expenses.show', $expense))
            ->delete(route('expenses.destroy', $expense));

        $response->assertRedirect(route('expenses.show', $expense));
        $response->assertSessionHas('error', __('app.unexpected_error'));
    }
}
