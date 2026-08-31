<?php

namespace Tests\Feature\Banking;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Banking\Actions\UnreconcileTransactionAction;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Exceptions\NotReconciledException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class UnreconcileTransactionTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        Account::create(['organization_id' => $this->organization->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '6530', 'name' => 'Software', 'type' => AccountType::Expense->value]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => Account::where('code', '1020')->first()->id,
            'name' => 'Main Account',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'currency' => 'CHF',
            'balance' => 10000.00,
        ]);
    }

    public function test_it_unreconciles_an_invoice_payment(): void
    {
        $client = Contact::create(['organization_id' => $this->organization->id, 'name' => 'Acme AG']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => now()->addMonth()->toDateString(),
            'subtotal' => 5000.00,
            'vat_amount' => 0,
            'total' => 5000.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment from Acme AG',
            'amount' => 5000.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-2026-001',
        ]);

        $reconciled = app(ReconciliationService::class)->reconcileWithInvoice($transaction, $invoice);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $originalRef = $reconciled->journalEntry->reference;

        $result = app(UnreconcileTransactionAction::class)->execute($reconciled);

        $this->assertFalse($result->is_reconciled);
        $this->assertNull($result->journal_entry_id);
        $this->assertNull($result->matched_invoice_id);
        $this->assertEquals('10000.00', $result->bankAccount->balance);
        $this->assertEquals(InvoiceStatus::Sent, $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->payments()->count());

        $reversal = JournalEntry::where('organization_id', $this->organization->id)
            ->where('reference', 'REV-'.$originalRef)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertTrue($reversal->is_posted);
    }

    public function test_it_reverts_an_overdue_invoice_to_overdue_not_sent(): void
    {
        $client = Contact::create(['organization_id' => $this->organization->id, 'name' => 'Acme AG']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-002',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => now()->subMonth()->toDateString(),
            'subtotal' => 5000.00,
            'vat_amount' => 0,
            'total' => 5000.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment from Acme AG',
            'amount' => 5000.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-2026-002',
        ]);

        $reconciled = app(ReconciliationService::class)->reconcileWithInvoice($transaction, $invoice);
        app(UnreconcileTransactionAction::class)->execute($reconciled);

        $this->assertEquals(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_it_unreconciles_an_expense_payment(): void
    {
        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'GitHub Pro',
            'amount' => 200.00,
            'vat_amount' => 0,
            'date' => '2026-03-12',
            'vendor' => 'GitHub',
            'status' => ExpenseStatus::Approved,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-12',
            'description' => 'GitHub Pro subscription',
            'amount' => 200.00,
            'type' => BankTransactionType::Debit,
            'reference' => 'EXP-GITHUB',
        ]);

        $reconciled = app(ReconciliationService::class)->reconcileWithExpense($transaction, $expense, '6530');
        $this->assertEquals(ExpenseStatus::Posted, $expense->fresh()->status);

        $result = app(UnreconcileTransactionAction::class)->execute($reconciled);

        $this->assertFalse($result->is_reconciled);
        $this->assertNull($result->matched_expense_id);
        $this->assertEquals('10000.00', $result->bankAccount->balance);
        $this->assertEquals(ExpenseStatus::Approved, $expense->fresh()->status);
        $this->assertNull($expense->fresh()->journal_entry_id);
    }

    public function test_it_unreconciles_a_manual_contra_account_match(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-14',
            'description' => 'Misc income',
            'amount' => 1500.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'MISC-001',
        ]);

        $reconciled = app(ReconciliationService::class)->reconcileWithContraAccount($transaction, '3000');
        $this->assertEquals('11500.00', $reconciled->bankAccount->fresh()->balance);

        $result = app(UnreconcileTransactionAction::class)->execute($reconciled);

        $this->assertFalse($result->is_reconciled);
        $this->assertNull($result->journal_entry_id);
        $this->assertEquals('10000.00', $result->bankAccount->balance);
    }

    public function test_it_refuses_to_unreconcile_a_transaction_that_is_not_reconciled(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-14',
            'description' => 'Unreconciled',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
        ]);

        $this->expectException(NotReconciledException::class);
        app(UnreconcileTransactionAction::class)->execute($transaction);
    }

    public function test_unreconcile_route_reverts_the_transaction(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-14',
            'description' => 'Misc income',
            'amount' => 1500.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'MISC-002',
        ]);
        app(ReconciliationService::class)->reconcileWithContraAccount($transaction, '3000');

        $response = $this->actAsOrg()->post("/reconciliation/transactions/{$transaction->id}/unreconcile");

        $response->assertRedirect();
        $this->assertFalse($transaction->fresh()->is_reconciled);
    }
}
