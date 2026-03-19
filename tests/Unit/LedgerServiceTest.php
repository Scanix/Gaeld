<?php

namespace Tests\Unit;

use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledgerService;
    private Organization $organization;
    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = new LedgerService();

        $user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($user->id, ['role' => 'owner']);

        $this->accounts['bank'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020', 'name' => 'Bank Account CHF', 'type' => AccountType::Asset->value,
        ]);
        $this->accounts['ar'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value,
        ]);
        $this->accounts['revenue'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000', 'name' => 'Revenue from Services', 'type' => AccountType::Revenue->value,
        ]);
        $this->accounts['software'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6530', 'name' => 'Software and Subscriptions', 'type' => AccountType::Expense->value,
        ]);
        $this->accounts['office'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6500', 'name' => 'Office Supplies', 'type' => AccountType::Expense->value,
        ]);
    }

    public function test_balanced_entry_posts_successfully(): void
    {
        $entry = $this->ledgerService->postEntry($this->organization->id, [
            'date' => '2026-03-16',
            'reference' => 'INV-001',
            'description' => 'Test invoice',
        ], [
            ['account_id' => $this->accounts['ar']->id, 'debit' => 1000.00, 'credit' => 0],
            ['account_id' => $this->accounts['revenue']->id, 'debit' => 0, 'credit' => 1000.00],
        ]);

        $this->assertTrue($entry->is_posted);
        $this->assertTrue($entry->isBalanced());
        $this->assertCount(2, $entry->lines);
    }

    public function test_unbalanced_entry_throws_exception(): void
    {
        $this->expectException(UnbalancedEntryException::class);

        $this->ledgerService->postEntry($this->organization->id, [
            'date' => '2026-03-16',
            'reference' => 'INV-BAD',
        ], [
            ['account_id' => $this->accounts['ar']->id, 'debit' => 1000.00, 'credit' => 0],
            ['account_id' => $this->accounts['revenue']->id, 'debit' => 0, 'credit' => 500.00],
        ]);
    }

    public function test_post_invoice_creates_journal_entry(): void
    {
        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client AG',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Draft,
            'issue_date' => '2026-03-16',
            'due_date' => '2026-04-15',
            'subtotal' => 5000.00,
            'vat_amount' => 0,
            'total' => 5000.00,
            'currency' => 'CHF',
        ]);

        $result = app(FinalizeInvoiceAction::class)->execute($invoice);

        $this->assertEquals(InvoiceStatus::Sent, $result->status);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
    }

    public function test_post_expense_creates_journal_entry(): void
    {
        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'GitHub Pro',
            'amount' => 200.00,
            'vat_amount' => 0,
            'date' => '2026-03-10',
            'vendor' => 'GitHub',
            'status' => ExpenseStatus::Pending,
            'currency' => 'CHF',
        ]);

        $result = app(PostExpenseAction::class)->execute($expense, '6530');

        $this->assertEquals(ExpenseStatus::Posted, $result->status);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
    }

    public function test_post_bank_transaction_creates_journal_entry(): void
    {
        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Main Account',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bank_name' => 'UBS',
            'currency' => 'CHF',
            'balance' => 10000.00,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Client payment received',
            'amount' => 3000.00,
            'type' => BankTransaction::TYPE_CREDIT,
            'reference' => 'BNK-DEP-001',
        ]);

        $result = app(BankingService::class)->postBankTransaction($transaction, '3000');

        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
    }
}