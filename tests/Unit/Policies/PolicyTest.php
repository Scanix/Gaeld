<?php

namespace Tests\Unit\Policies;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Policies\AccountPolicy;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Policies\BankAccountPolicy;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Policies\ExpensePolicy;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Policies\InvoicePolicy;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class PolicyTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $memberUser;

    private User $outsiderUser;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);

        $this->memberUser = User::factory()->create();
        $this->organization->users()->attach($this->memberUser->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->memberUser, $this->organization, 'owner');

        $this->outsiderUser = User::factory()->create();
    }

    // ──────────────────────────────────────────────────────────────
    //  AccountPolicy
    // ──────────────────────────────────────────────────────────────

    public function test_account_policy_viewany_requires_organization_membership(): void
    {
        $policy = new AccountPolicy;

        $this->assertTrue($policy->viewAny($this->memberUser));
        $this->assertFalse($policy->viewAny($this->outsiderUser));
    }

    public function test_account_policy_view_requires_same_organization(): void
    {
        $policy = new AccountPolicy;

        $account = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank CHF',
            'type' => AccountType::Asset->value,
        ]);

        $this->assertTrue($policy->view($this->memberUser, $account));
        $this->assertFalse($policy->view($this->outsiderUser, $account));
    }

    public function test_account_policy_delete_denied_when_has_transaction_lines(): void
    {
        $policy = new AccountPolicy;

        $account = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank CHF',
            'type' => AccountType::Asset->value,
        ]);

        // No transaction lines → can delete
        $this->assertTrue($policy->delete($this->memberUser, $account));
    }

    // ──────────────────────────────────────────────────────────────
    //  InvoicePolicy
    // ──────────────────────────────────────────────────────────────

    public function test_invoice_policy_view_requires_same_organization(): void
    {
        $policy = new InvoicePolicy;

        $client = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-001',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '0.00',
            'vat_amount' => '0.00',
            'total' => '0.00',
            'currency' => 'CHF',
        ]);

        $this->assertTrue($policy->view($this->memberUser, $invoice));
        $this->assertFalse($policy->view($this->outsiderUser, $invoice));
    }

    public function test_invoice_policy_update_denied_for_non_editable_status(): void
    {
        $policy = new InvoicePolicy;

        $client = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client',
        ]);

        $paidInvoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-002',
            'status' => InvoiceStatus::Paid->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'CHF',
        ]);

        $this->assertFalse($policy->update($this->memberUser, $paidInvoice));
    }

    public function test_invoice_policy_update_allowed_for_draft(): void
    {
        $policy = new InvoicePolicy;

        $client = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client',
        ]);

        $draftInvoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-003',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'CHF',
        ]);

        $this->assertTrue($policy->update($this->memberUser, $draftInvoice));
    }

    // ──────────────────────────────────────────────────────────────
    //  ExpensePolicy
    // ──────────────────────────────────────────────────────────────

    public function test_expense_policy_view_requires_same_organization(): void
    {
        $policy = new ExpensePolicy;

        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'amount' => '50.00',
            'vat_amount' => '0.00',
            'date' => now()->toDateString(),
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        $this->assertTrue($policy->view($this->memberUser, $expense));
        $this->assertFalse($policy->view($this->outsiderUser, $expense));
    }

    public function test_expense_policy_update_denied_for_posted_expense(): void
    {
        $policy = new ExpensePolicy;

        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'amount' => '50.00',
            'vat_amount' => '0.00',
            'date' => now()->toDateString(),
            'status' => ExpenseStatus::Posted->value,
            'currency' => 'CHF',
        ]);

        $this->assertFalse($policy->update($this->memberUser, $expense));
    }

    // ──────────────────────────────────────────────────────────────
    //  BankAccountPolicy
    // ──────────────────────────────────────────────────────────────

    public function test_bank_account_policy_view_requires_same_organization(): void
    {
        $policy = new BankAccountPolicy;

        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Main Bank Account',
            'iban' => 'CH56 0483 5012 3456 7800 9',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        $this->assertTrue($policy->view($this->memberUser, $bankAccount));
        $this->assertFalse($policy->view($this->outsiderUser, $bankAccount));
    }

    public function test_bank_account_policy_delete_denied_when_has_transactions(): void
    {
        $policy = new BankAccountPolicy;

        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Main Bank Account',
            'iban' => 'CH56 0483 5012 3456 7800 9',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        // No transactions → can delete
        $this->assertTrue($policy->delete($this->memberUser, $bankAccount));
    }
}
