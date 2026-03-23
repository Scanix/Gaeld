<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use Mockery;
use Tests\TestCase;

class PostExpenseActionTest extends TestCase
{
    private PostExpenseAction $action;

    private $expenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expenseService = Mockery::mock(ExpenseService::class);
        $this->action = new PostExpenseAction($this->expenseService);
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

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 100;

        $this->expenseService
            ->shouldReceive('postToLedger')
            ->once()
            ->withArgs(function ($exp, RecordExpensePaymentData $data) use ($expense) {
                return $exp === $expense
                    && $data->amount === '500'
                    && $data->expenseAccountCode === AccountCode::GENERAL_EXPENSE
                    && $data->bankAccountCode === AccountCode::BANK_CASH
                    && $data->reference === 'EXP-42'
                    && $data->description === 'Office supplies';
            })
            ->andReturn($journalEntry);

        $expense->shouldReceive('fresh')->once()->andReturn($expense);

        $result = $this->action->execute($expense, AccountCode::GENERAL_EXPENSE);

        $this->assertSame($expense, $result);
    }

    public function test_uses_description_fallback_to_category(): void
    {
        $expense = Mockery::mock(Expense::class)->makePartial();
        $expense->status = ExpenseStatus::Approved;
        $expense->organization_id = 'org-1';
        $expense->amount = 200;
        $expense->description = null;
        $expense->category = 'Travel';
        $expense->id = 43;
        $expense->date = now();

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 101;

        $this->expenseService
            ->shouldReceive('postToLedger')
            ->once()
            ->withArgs(function ($exp, RecordExpensePaymentData $data) {
                return $data->description === 'Travel'
                    && $data->expenseAccountCode === AccountCode::GENERAL_EXPENSE;
            })
            ->andReturn($journalEntry);

        $expense->shouldReceive('fresh')->once()->andReturn($expense);

        $this->action->execute($expense, AccountCode::GENERAL_EXPENSE);
    }
}
