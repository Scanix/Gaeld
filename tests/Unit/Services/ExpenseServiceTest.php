<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
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

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1170',
            'name' => 'Input VAT',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3900',
            'name' => 'Rounding Difference',
            'type' => AccountType::Revenue->value,
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

    public function test_non_cash_chf_expense_credits_exact_gross_amount_without_revenue_residual(): void
    {
        $vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'STANDARD',
            'is_default' => true,
        ]);
        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Office Supplies',
            'description' => 'Exact gross test',
            'amount' => '120.00',
            'vat_amount' => '9.72',
            'vat_rate_id' => $vatRate->id,
            'date' => '2026-03-08',
            'vendor' => 'Test supplier',
            'payment_method' => 'Other',
            'status' => ExpenseStatus::Approved,
            'currency' => 'CHF',
        ]);

        $journalEntry = app(ExpenseService::class)->postToLedger($expense, new RecordExpensePaymentData(
            amount: '120.00',
            paymentDate: '2026-03-08',
            reference: 'REC-EXP-GROSS-1',
            description: 'Exact gross test',
            expenseAccountCode: '6530',
            bankAccountCode: '1020',
        ));

        $lines = $journalEntry->fresh('lines')->lines;

        $this->assertSame('129.72', $lines->firstWhere('account_id', Account::where('code', '1020')->value('id'))->credit);
        $this->assertFalse($lines->contains(fn ($line) => $line->account_id === Account::where('code', '3900')->value('id')));
    }

    public function test_cash_chf_expense_keeps_five_centime_rounding_on_configured_account(): void
    {
        $vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'STANDARD',
            'is_default' => true,
        ]);
        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Office Supplies',
            'description' => 'Cash rounding test',
            'amount' => '120.00',
            'vat_amount' => '9.72',
            'vat_rate_id' => $vatRate->id,
            'date' => '2026-03-08',
            'vendor' => 'Cash supplier',
            'payment_method' => 'Cash',
            'status' => ExpenseStatus::Approved,
            'currency' => 'CHF',
        ]);

        $journalEntry = app(ExpenseService::class)->postToLedger($expense, new RecordExpensePaymentData(
            amount: '120.00',
            paymentDate: '2026-03-08',
            reference: 'REC-EXP-CASH-1',
            description: 'Cash rounding test',
            expenseAccountCode: '6530',
            bankAccountCode: '1020',
        ));

        $lines = $journalEntry->fresh('lines')->lines;

        $this->assertSame('129.70', $lines->firstWhere('account_id', Account::where('code', '1020')->value('id'))->credit);
        $this->assertSame('0.02', $lines->firstWhere('account_id', Account::where('code', '3900')->value('id'))->credit);
    }
}
