<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_expense_flow(): void
    {
        // Setup
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        $expenseAccount = Account::create([
            'organization_id' => $org->id,
            'code' => '6530',
            'name' => 'Software and Subscriptions',
            'type' => Account::TYPE_EXPENSE,
        ]);

        $bank = Account::create([
            'organization_id' => $org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => Account::TYPE_ASSET,
        ]);

        $vatRate = VatRate::create([
            'organization_id' => $org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        // 1. Create expense
        $action = new CreateExpenseAction();
        $expense = $action->execute([
            'organization_id' => $org->id,
            'vat_rate_id' => $vatRate->id,
            'category' => 'Software and Subscriptions',
            'description' => 'Adobe Creative Cloud',
            'amount' => 700.00,
            'vat_amount' => 56.70,
            'date' => '2026-03-16',
            'vendor' => 'Adobe Inc.',
        ]);

        $this->assertEquals(Expense::STATUS_PENDING, $expense->status);
        $this->assertEquals('700.00', $expense->amount);

        // 2. Post expense to ledger
        $expenseService = app(ExpenseService::class);
        $expense = $expenseService->postExpense($expense, '6530');

        $this->assertEquals(Expense::STATUS_POSTED, $expense->status);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertTrue($expense->journalEntry->isBalanced());

        // Verify ledger entries
        $lines = $expense->journalEntry->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('account_id', $expenseAccount->id);
        $creditLine = $lines->firstWhere('account_id', $bank->id);

        $this->assertEquals('700.00', $debitLine->debit);
        $this->assertEquals('700.00', $creditLine->credit);
    }

    public function test_cannot_post_already_posted_expense(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        Account::create([
            'organization_id' => $org->id,
            'code' => '6530',
            'name' => 'Software',
            'type' => Account::TYPE_EXPENSE,
        ]);

        Account::create([
            'organization_id' => $org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => Account::TYPE_ASSET,
        ]);

        $action = new CreateExpenseAction();
        $expense = $action->execute([
            'organization_id' => $org->id,
            'category' => 'Software',
            'amount' => 100.00,
            'date' => '2026-03-16',
        ]);

        $expenseService = app(ExpenseService::class);
        $expenseService->postExpense($expense, '6530');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already posted');

        $expenseService->postExpense($expense->fresh(), '6530');
    }
}
