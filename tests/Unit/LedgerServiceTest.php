<?php

namespace Tests\Unit;

use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Enums\BankTransactionType;
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
use App\Domains\Invoicing\Models\InvoiceLine;
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
        $entry = $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-16',
            reference: 'INV-001',
            description: 'Test invoice',
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '1000.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '1000.00'),
            ],
        ));

        $this->assertTrue($entry->is_posted);
        $this->assertTrue($entry->isBalanced());
        $this->assertCount(2, $entry->lines);
    }

    public function test_unbalanced_entry_throws_exception(): void
    {
        $this->expectException(UnbalancedEntryException::class);

        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-16',
            reference: 'INV-BAD',
            description: null,
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '1000.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '500.00'),
            ],
        ));
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

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulting services',
            'quantity' => 1,
            'unit_price' => 5000.00,
            'amount' => 5000.00,
            'vat_amount' => 0,
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
            'status' => ExpenseStatus::Approved,
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
            'type' => BankTransactionType::Credit,
            'reference' => 'BNK-DEP-001',
        ]);

        $result = app(BankingService::class)->postBankTransaction($transaction, '3000');

        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
    }

    public function test_account_balance_debit_normal_returns_debits_minus_credits(): void
    {
        // Asset account is debit-normal: balance = debits - credits
        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'BAL-001',
            description: 'Opening balance',
            lines: [
                new JournalLineData(accountId: $this->accounts['bank']->id, debit: '1000.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '1000.00'),
            ],
        ));

        $balance = $this->ledgerService->accountBalance($this->accounts['bank']->id);

        $this->assertSame('1000.00', $balance);
    }

    public function test_account_balance_credit_normal_returns_credits_minus_debits(): void
    {
        // Revenue account is credit-normal: balance = credits - debits
        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'BAL-002',
            description: 'Service revenue',
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '500.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '500.00'),
            ],
        ));

        $balance = $this->ledgerService->accountBalance($this->accounts['revenue']->id);

        $this->assertSame('500.00', $balance);
    }

    public function test_reversal_creates_contra_entry_and_nets_to_zero(): void
    {
        $entry = $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-05',
            reference: 'INV-REV-001',
            description: 'Invoice to be reversed',
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '750.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '750.00'),
            ],
        ));

        $this->ledgerService->reverseEntry($entry, 'Test reversal');

        // Net balance for both accounts should be zero after reversal
        $this->assertSame('0.00', $this->ledgerService->accountBalance($this->accounts['ar']->id));
        $this->assertSame('0.00', $this->ledgerService->accountBalance($this->accounts['revenue']->id));
    }

    public function test_create_draft_does_not_affect_account_balance(): void
    {
        $draft = $this->ledgerService->createDraft($this->organization->id, new JournalEntryData(
            date: '2026-03-10',
            reference: 'DRAFT-001',
            description: 'Draft entry',
            lines: [
                new JournalLineData(accountId: $this->accounts['bank']->id, debit: '2000.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '2000.00'),
            ],
        ));

        $this->assertFalse($draft->is_posted);
        // Draft entries must not appear in account balance
        $this->assertSame('0.00', $this->ledgerService->accountBalance($this->accounts['bank']->id));
    }

    public function test_post_draft_marks_entry_as_posted(): void
    {
        $draft = $this->ledgerService->createDraft($this->organization->id, new JournalEntryData(
            date: '2026-03-10',
            reference: 'DRAFT-POST-001',
            description: 'Draft to post',
            lines: [
                new JournalLineData(accountId: $this->accounts['bank']->id, debit: '300.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '300.00'),
            ],
        ));

        $posted = $this->ledgerService->postDraft($draft);

        $this->assertTrue($posted->is_posted);
        $this->assertSame('300.00', $this->ledgerService->accountBalance($this->accounts['bank']->id));
    }

    public function test_post_draft_rejects_already_posted_entry(): void
    {
        $entry = $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-10',
            reference: 'ALREADY-POSTED',
            description: 'Already posted',
            lines: [
                new JournalLineData(accountId: $this->accounts['bank']->id, debit: '100.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '100.00'),
            ],
        ));

        $this->expectException(\App\Domains\Accounting\Exceptions\AlreadyPostedException::class);
        $this->ledgerService->postDraft($entry);
    }

    public function test_trial_balance_reflects_posted_entries(): void
    {
        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-15',
            reference: 'TB-001',
            description: 'Trial balance test entry',
            lines: [
                new JournalLineData(accountId: $this->accounts['bank']->id, debit: '1500.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '1500.00'),
            ],
        ));

        $trialBalance = $this->ledgerService->trialBalance($this->organization->id);

        $codes = array_column($trialBalance, 'account_code');
        $this->assertContains('1020', $codes); // bank (debit-normal, debit side)
        $this->assertContains('3000', $codes); // revenue (credit-normal, credit side)

        $bankRow = array_values(array_filter($trialBalance, fn ($r) => $r['account_code'] === '1020'))[0];
        $revenueRow = array_values(array_filter($trialBalance, fn ($r) => $r['account_code'] === '3000'))[0];

        $this->assertSame('1500.00', $bankRow['debit']);
        $this->assertSame('0', $bankRow['credit']);
        $this->assertSame('0', $revenueRow['debit']);
        $this->assertSame('1500.00', $revenueRow['credit']);
    }
}