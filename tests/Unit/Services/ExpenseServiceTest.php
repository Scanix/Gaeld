<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Expense Service Org',
            'currency' => 'CHF',
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6530',
            'name' => 'Software Expense',
            'type' => AccountType::Expense->value,
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
    }

    public function test_post_to_ledger_marks_expense_posted_and_creates_balanced_entry(): void
    {
        $service = app(ExpenseService::class);

        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'Figma subscription',
            'amount' => 120.00,
            'vat_amount' => 0,
            'date' => '2026-03-08',
            'vendor' => 'Figma',
            'status' => ExpenseStatus::Approved,
            'currency' => 'CHF',
        ]);

        $journalEntry = $service->postToLedger($expense, new RecordExpensePaymentData(
            amount: '120.00',
            paymentDate: '2026-03-08',
            reference: 'REC-EXP-1',
            description: 'Expense payment for Figma subscription',
            expenseAccountCode: '6530',
            bankAccountCode: '1020',
        ));

        $expense->refresh();

        $this->assertSame(ExpenseStatus::Posted, $expense->status);
        $this->assertSame($journalEntry->id, $expense->journal_entry_id);
        $this->assertTrue($journalEntry->fresh('lines')->isBalanced());
    }
}
