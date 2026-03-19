<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PostExpenseActionTest extends TestCase
{
    private PostExpenseAction $action;

    private $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->action = new PostExpenseAction($this->ledgerService);
    }

    public function test_rejects_already_posted_expense(): void
    {
        $expense = Mockery::mock(Expense::class)->makePartial();
        $expense->status = ExpenseStatus::Posted;

        $this->expectException(InvalidExpenseStateException::class);
        $this->expectExceptionMessage('Expense is already posted.');

        $this->action->execute($expense, AccountCode::GENERAL_EXPENSE);
    }

    public function test_posts_approved_expense_to_ledger(): void
    {
        $expense = Mockery::mock(Expense::class)->makePartial();
        $expense->status = ExpenseStatus::Approved;
        $expense->organization_id = 'org-1';
        $expense->amount = 500;
        $expense->description = 'Office supplies';
        $expense->category = 'Supplies';
        $expense->id = 42;
        $expense->date = now();

        $expenseAccount = Mockery::mock(Account::class)->makePartial();
        $expenseAccount->id = 10;
        $bankAccount = Mockery::mock(Account::class)->makePartial();
        $bankAccount->id = 20;

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 100;

        $this->ledgerService
            ->shouldReceive('resolveAccount')
            ->twice()
            ->andReturn($expenseAccount, $bankAccount);

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $expense->shouldReceive('update')->once();
        $expense->shouldReceive('fresh')->once()->andReturn($expense);

        $result = $this->action->execute($expense, AccountCode::GENERAL_EXPENSE);

        $this->assertSame($expense, $result);
    }

    public function test_uses_default_bank_account_code(): void
    {
        $expense = Mockery::mock(Expense::class)->makePartial();
        $expense->status = ExpenseStatus::Approved;
        $expense->organization_id = 'org-1';
        $expense->amount = 200;
        $expense->description = null;
        $expense->category = 'Travel';
        $expense->id = 43;
        $expense->date = now();

        $expenseAccount = Mockery::mock(Account::class)->makePartial();
        $expenseAccount->id = 10;
        $bankAccount = Mockery::mock(Account::class)->makePartial();
        $bankAccount->id = 20;

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 101;

        $this->ledgerService
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::GENERAL_EXPENSE)
            ->once()
            ->andReturn($expenseAccount);

        $this->ledgerService
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::BANK_CASH)
            ->once()
            ->andReturn($bankAccount);

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $expense->shouldReceive('update')->once();
        $expense->shouldReceive('fresh')->once()->andReturn($expense);

        $this->action->execute($expense, AccountCode::GENERAL_EXPENSE);
    }
}
