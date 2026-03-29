<?php

namespace Tests\Feature\Organizations;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class CoreHttpFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $user;

    private Organization $organization;

    private Customer $customer;

    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Core HTTP Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->organization, 'owner');

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6530',
            'name' => 'Software Expense',
            'type' => AccountType::Expense->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '2200',
            'name' => 'VAT Output',
            'type' => AccountType::Liability->value,
        ]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'HTTP Client AG',
        ]);
    }

    public function test_invoice_http_flow_uses_authenticated_request_pipeline(): void
    {
        $create = $this->asCurrentOrg()->post('/invoices', [
            'customer_id' => $this->customer->id,
            'number' => 'INV-HTTP-001',
            'issue_date' => '2026-03-10',
            'due_date' => '2026-03-31',
            'currency' => 'CHF',
            'lines' => [
                [
                    'description' => 'HTTP consulting',
                    'quantity' => 1,
                    'unit_price' => 250.00,
                    'vat_rate_id' => $this->vatRate->id,
                ],
            ],
        ]);

        $invoice = Invoice::where('number', 'INV-HTTP-001')->firstOrFail();

        $create->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);

        $this->asCurrentOrg()->post("/invoices/{$invoice->id}/finalize")
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);

        $this->asCurrentOrg()->post("/invoices/{$invoice->id}/payment", [
            'amount' => (string) $invoice->total,
            'payment_date' => '2026-03-15',
            'payment_method' => 'bank',
            'reference' => 'INV-HTTP-PAY-1',
            'bank_account_code' => '1020',
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_expense_http_flow_uses_authenticated_request_pipeline(): void
    {
        $create = $this->asCurrentOrg()->post('/expenses', [
            'category' => 'Software',
            'description' => 'HTTP expense',
            'amount' => 120.00,
            'vat_amount' => 0,
            'date' => '2026-03-12',
            'vendor' => 'GitHub',
            'currency' => 'CHF',
        ]);

        $expense = Expense::where('description', 'HTTP expense')->firstOrFail();

        $create->assertRedirect(route('expenses.show', $expense));
        $this->assertSame(ExpenseStatus::Pending, $expense->status);

        $this->asCurrentOrg()->post("/expenses/{$expense->id}/approve")
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Approved, $expense->status);

        $this->asCurrentOrg()->post("/expenses/{$expense->id}/post", [
            'expense_account_code' => '6530',
        ])->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Posted, $expense->status);
        $this->assertNotNull($expense->journal_entry_id);
    }

    public function test_banking_http_flow_creates_bank_account_and_records_transaction(): void
    {
        $ledgerAccountId = Account::where('organization_id', $this->organization->id)
            ->where('code', '1020')
            ->value('id');

        $create = $this->asCurrentOrg()->post('/banking', [
            'name' => 'Main HTTP Bank',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bank_name' => 'UBS',
            'account_id' => $ledgerAccountId,
            'currency' => 'CHF',
        ]);

        $bankAccount = BankAccount::where('name', 'Main HTTP Bank')->firstOrFail();

        $create->assertRedirect(route('banking.show', $bankAccount));

        $this->asCurrentOrg()->post("/banking/{$bankAccount->id}/transactions", [
            'date' => '2026-03-14',
            'description' => 'HTTP transfer',
            'amount' => 150.00,
            'type' => 'credit',
            'reference' => 'BNK-HTTP-1',
            'contra_account_code' => '3000',
        ])->assertRedirect(route('banking.show', $bankAccount));

        $transaction = BankTransaction::where('bank_account_id', $bankAccount->id)->firstOrFail();

        $this->assertSame(BankTransactionType::Credit, $transaction->type);
        $this->assertNotNull($transaction->journal_entry_id);
        $this->assertSame('150.00', $bankAccount->fresh()->balance);
    }

    public function test_reconciliation_manual_route_marks_transaction_reconciled(): void
    {
        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => Account::where('organization_id', $this->organization->id)->where('code', '1020')->value('id'),
            'name' => 'Recon HTTP Bank',
            'currency' => 'CHF',
            'balance' => 0,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => '2026-03-16',
            'description' => 'Manual reconciliation',
            'amount' => 99.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'REC-HTTP-1',
            'is_reconciled' => false,
        ]);

        $this->asCurrentOrg()->post("/reconciliation/transactions/{$transaction->id}/manual", [
            'contra_account_code' => '3000',
        ])->assertRedirect();

        $transaction->refresh();
        $this->assertTrue($transaction->is_reconciled);
        $this->assertNotNull($transaction->journal_entry_id);
    }

    private function asCurrentOrg(): self
    {
        return $this->actingAs($this->user)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }
}
