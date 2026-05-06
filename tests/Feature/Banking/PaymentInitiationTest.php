<?php

namespace Tests\Feature\Banking;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $bank = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $bank->id,
            'name' => 'Main',
            'iban' => 'CH9300762011623852957',
            'currency' => 'CHF',
            'balance' => 1000,
        ]);
    }

    private function createPayableExpense(?string $supplierIban = 'CH4431999123000889012'): Expense
    {
        $supplier = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Supplier '.uniqid(),
            'email' => uniqid('s').'@example.com',
            'country' => 'CH',
            'currency' => 'CHF',
            'iban' => $supplierIban,
        ]);

        return Expense::create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
            'category' => 'consulting',
            'description' => 'Audit fee',
            'amount' => '750.00',
            'vat_amount' => '0.00',
            'date' => now()->toDateString(),
            'status' => ExpenseStatus::Approved->value,
            'currency' => 'CHF',
        ]);
    }

    public function test_index_lists_payable_expenses_for_current_org(): void
    {
        $this->createPayableExpense();
        // Excluded: no supplier IBAN
        $this->createPayableExpense(null);
        // Excluded: cross-org leak check
        $otherOrgExpense = Expense::create([
            'organization_id' => Organization::factory()->create()->id,
            'category' => 'consulting',
            'description' => 'Other org',
            'amount' => '99.00',
            'vat_amount' => '0.00',
            'date' => now()->toDateString(),
            'status' => ExpenseStatus::Approved->value,
            'currency' => 'CHF',
        ]);

        $this->actAsOrg()
            ->get('/payments/outgoing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Banking/PaymentsOutgoing')
                ->has('expenses', 1)
                ->where('expenses.0.description', 'Audit fee')
                ->has('bankAccounts', 1)
            );
    }

    public function test_download_returns_pain001_xml(): void
    {
        $expense = $this->createPayableExpense();

        $response = $this->actAsOrg()->post('/payments/outgoing/download', [
            'bank_account_id' => $this->bankAccount->id,
            'expense_ids' => [$expense->id],
            'execution_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=utf-8');
        $body = (string) $response->getContent();
        $this->assertStringContainsString('urn:iso:std:iso:20022:tech:xsd:pain.001.001.09', $body);
        $this->assertStringContainsString('CH9300762011623852957', $body);
        $this->assertStringContainsString('CH4431999123000889012', $body);
        $this->assertStringContainsString('<CtrlSum>750.00</CtrlSum>', $body);
    }

    public function test_download_rejects_empty_selection(): void
    {
        $this->actAsOrg()->post('/payments/outgoing/download', [
            'bank_account_id' => $this->bankAccount->id,
            'expense_ids' => [],
        ])->assertSessionHasErrors('expense_ids');
    }
}
