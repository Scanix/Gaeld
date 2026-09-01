<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Enums\ContactType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Tests\Security\SecurityTestCase;

class BusinessDocumentApiTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_it_creates_and_lists_contacts_for_invoice_integrations(): void
    {
        $payload = [
            'type' => ContactType::Organization->value,
            'name' => 'Integration Customer AG',
            'email' => 'customer@example.test',
            'vat_number' => 'CHE-123.456.789 MWST',
        ];
        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'contact-quickstart-1')
            ->postJson('/api/v1/contacts', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Integration Customer AG')
            ->assertJsonPath('data.email', 'customer@example.test');

        $replay = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'contact-quickstart-1')
            ->postJson('/api/v1/contacts', $payload);
        $replay->assertCreated();
        $this->assertSame($response->json('data.id'), $replay->json('data.id'));

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'contact-quickstart-1')
            ->postJson('/api/v1/contacts', [...$payload, 'name' => 'Changed Customer AG'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->withToken($this->tokenA)
            ->getJson('/api/v1/contacts?search=Integration')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_imports_camt053_and_replays_the_same_import(): void
    {
        $ledgerAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $bankAccount = $this->orgA->bankAccounts()->create([
            'account_id' => $ledgerAccount->id,
            'name' => 'Main bank',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);
        $fixture = file_get_contents(base_path('tests/fixtures/camt053_sample.xml'));
        $this->assertIsString($fixture);

        $first = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-quickstart-1')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', $fixture),
            ]);

        $first->assertCreated();
        $this->assertGreaterThan(0, $first->json('data.transaction_count'));

        $second = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-quickstart-1')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', $fixture),
            ]);

        $second->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-quickstart-1')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', '<different />'),
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_conflict');
    }

    public function test_finalized_invoice_exposes_its_generated_journal_entry(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'type' => ContactType::Organization->value,
            'name' => 'Invoice Customer AG',
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts receivable',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $customer->id,
            'number' => 'API-INV-001',
            'status' => InvoiceStatus::Draft,
            'issue_date' => '2026-08-21',
            'due_date' => '2026-09-20',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'CHF',
        ]);
        $invoice->lines()->create([
            'description' => 'Integration service',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'amount' => '100.00',
            'vat_amount' => '0.00',
        ]);

        $response = $this->withToken($this->tokenA)
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.journal_entry.id'));
        $this->assertSame('posted', $response->json('data.journal_entry.status'));

        $replay = $this->withToken($this->tokenA)
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize");
        $replay->assertOk();
        $this->assertSame($response->json('data.journal_entry.id'), $replay->json('data.journal_entry.id'));
    }

    public function test_it_creates_a_reverse_charge_invoice_for_an_eu_customer(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'type' => ContactType::Organization->value,
            'name' => 'EU Integration Customer GmbH',
            'country' => 'DE',
            'vat_number' => 'DE123456789',
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts receivable',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'reverse-charge-api-1')
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->uuid,
                'number' => 'API-RC-001',
                'issue_date' => '2026-08-31',
                'due_date' => '2026-09-30',
                'currency' => 'CHF',
                'tax_treatment' => 'reverse_charge',
                'lines' => [[
                    'description' => 'EU consulting service',
                    'quantity' => 1,
                    'unit_price' => '1000.00',
                ]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tax_treatment', 'reverse_charge')
            ->assertJsonPath('data.vat_amount', '0.00')
            ->assertJsonPath('data.total', '1000.00');

        $invoice = Invoice::where('number', 'API-RC-001')->firstOrFail();
        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'reverse-charge-api-finalize-1')
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseCount('vat_entries', 0);
    }

    public function test_posted_expense_exposes_its_generated_journal_entry(): void
    {
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '6500',
            'name' => 'Office supplies',
            'type' => AccountType::Expense->value,
        ]);

        $expense = $this->withToken($this->tokenA)
            ->postJson('/api/v1/expenses', [
                'category' => 'Office supplies',
                'description' => 'Integration expense',
                'amount' => '50.00',
                'date' => '2026-08-21',
                'vendor' => 'Supplier AG',
                'currency' => 'CHF',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/expenses/{$expense}/approve")
            ->assertOk();

        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'expense-post-replay')
            ->postJson("/api/v1/expenses/{$expense}/post-to-ledger", [
                'expense_account_code' => '6500',
                'bank_account_code' => '1020',
            ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.journal_entry.id'));
        $this->assertSame('posted', $response->json('data.journal_entry.status'));

        $replay = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'expense-post-replay')
            ->postJson("/api/v1/expenses/{$expense}/post-to-ledger", [
                'expense_account_code' => '6500',
                'bank_account_code' => '1020',
            ]);
        $replay->assertOk();
        $this->assertSame($response->json('data.journal_entry.id'), $replay->json('data.journal_entry.id'));
    }

    public function test_api_expense_posts_exact_gross_amount_without_rounding_residual(): void
    {
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '6500',
            'name' => 'Office supplies',
            'type' => AccountType::Expense->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1170',
            'name' => 'Input VAT',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '3900',
            'name' => 'Rounding Difference',
            'type' => AccountType::Revenue->value,
        ]);
        $vatRate = VatRate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $expense = $this->withToken($this->tokenA)
            ->postJson('/api/v1/expenses', [
                'category' => 'Office supplies',
                'description' => 'Exact gross API expense',
                'amount' => '120.00',
                'vat_rate_id' => $vatRate->uuid,
                'date' => '2026-08-21',
                'vendor' => 'Supplier AG',
                'currency' => 'CHF',
                'payment_method' => 'other',
            ])
            ->assertCreated()
            ->assertJsonPath('data.vat_amount', '9.72')
            ->json('data.id');

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/expenses/{$expense}/approve")
            ->assertOk();

        $response = $this->withToken($this->tokenA)
            ->postJson("/api/v1/expenses/{$expense}/post-to-ledger", [
                'expense_account_code' => '6500',
                'bank_account_code' => '1020',
            ])
            ->assertOk();

        $lines = collect($response->json('data.journal_entry.lines'));

        $this->assertSame('129.72', $lines->firstWhere('account_code', '1020')['credit']);
        $this->assertFalse($lines->contains(fn (array $line): bool => $line['account_code'] === '3900'));
    }

    public function test_malformed_camt053_is_rejected_without_an_import_record(): void
    {
        $ledgerAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $bankAccount = $this->orgA->bankAccounts()->create([
            'account_id' => $ledgerAccount->id,
            'name' => 'Main bank',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-invalid-1')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', '<invalid />'),
            ]);

        $response->assertStatus(422)->assertJsonPath('code', 'bank_import_invalid');
        $this->assertDatabaseCount('bank_imports', 0);
    }

    public function test_reimporting_the_same_statement_with_a_new_key_does_not_duplicate_transactions(): void
    {
        $ledgerAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $bankAccount = $this->orgA->bankAccounts()->create([
            'account_id' => $ledgerAccount->id,
            'name' => 'Main bank',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);
        $fixture = file_get_contents(base_path('tests/fixtures/camt053_sample.xml'));
        $this->assertIsString($fixture);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-dedup-1')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', $fixture),
            ])
            ->assertCreated();

        $second = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'camt-dedup-2')
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', $fixture),
            ]);

        $second->assertCreated()->assertJsonPath('data.transaction_count', 0);
        $this->assertDatabaseCount('bank_transactions', 3);
    }

    public function test_failed_invoice_finalization_releases_its_idempotency_reservation(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'type' => ContactType::Organization->value,
            'name' => 'Retry Customer AG',
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts receivable',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Sent,
            'number' => 'RETRY-INV-001',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
        ]);
        $invoice->lines()->create([
            'description' => 'Retry service',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'amount' => '100.00',
            'vat_amount' => '0.00',
        ]);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'retry-invoice-finalize')
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize")
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_invoice_state');

        $invoice->update(['status' => InvoiceStatus::Draft]);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'retry-invoice-finalize')
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize")
            ->assertOk()
            ->assertJsonPath('data.journal_entry.status', 'posted');
    }

    public function test_failed_expense_post_releases_its_idempotency_reservation(): void
    {
        $expense = Expense::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => ExpenseStatus::Approved,
            'amount' => '50.00',
            'date' => '2026-08-21',
        ]);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'retry-expense-post')
            ->postJson("/api/v1/expenses/{$expense->id}/post-to-ledger", [
                'expense_account_code' => '6500',
                'bank_account_code' => '1020',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'account_not_found');

        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '6500',
            'name' => 'Office supplies',
            'type' => AccountType::Expense->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'retry-expense-post')
            ->postJson("/api/v1/expenses/{$expense->id}/post-to-ledger", [
                'expense_account_code' => '6500',
                'bank_account_code' => '1020',
            ])
            ->assertOk()
            ->assertJsonPath('data.journal_entry.status', 'posted');
    }
}
