<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankMatchType;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\MatchingService;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Matching Test Org',
            'currency' => 'CHF',
        ]);

        $bankLedgerAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $bankLedgerAccount->id,
            'name' => 'Main Bank',
            'currency' => 'CHF',
            'balance' => 0,
        ]);
    }

    public function test_qr_reference_match_is_prioritized_and_persisted(): void
    {
        $service = app(MatchingService::class);
        $customer = $this->createCustomer('Acme AG');

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 500.00,
            'vat_amount' => 0,
            'total' => 500.00,
            'currency' => 'CHF',
            'qr_reference' => 'RF18539007547034',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2026-002',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-02',
            'due_date' => '2026-03-31',
            'subtotal' => 500.00,
            'vat_amount' => 0,
            'total' => 500.00,
            'currency' => 'CHF',
        ]);

        $transaction = $this->createTransaction([
            'amount' => 500.00,
            'structured_reference' => 'RF18539007547034',
            'debtor_name' => 'Acme AG',
            'reference' => 'INV-2026-001',
        ]);

        $matches = $service->findAndStoreMatches($transaction);

        $this->assertCount(1, $matches);
        $this->assertSame($invoice->id, $matches->first()->invoice_id);
        $this->assertSame(MatchConfidence::QrReference->value, $matches->first()->confidence);
        $this->assertSame(BankMatchType::QrReference, $matches->first()->match_type);
    }

    public function test_amount_and_customer_match_replaces_existing_unconfirmed_matches(): void
    {
        $service = app(MatchingService::class);
        $customer = $this->createCustomer('Globex AG');
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2026-010',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 750.00,
            'vat_amount' => 0,
            'total' => 750.00,
            'currency' => 'CHF',
        ]);

        $oldInvoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-OLD',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'total' => 100.00,
            'currency' => 'CHF',
        ]);

        $transaction = $this->createTransaction([
            'amount' => 750.00,
            'debtor_name' => 'Globex AG',
            'reference' => null,
        ]);

        BankMatch::create([
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $oldInvoice->id,
            'confidence' => MatchConfidence::Heuristic->value,
            'match_type' => BankMatchType::Heuristic,
            'is_confirmed' => false,
        ]);

        $matches = $service->findAndStoreMatches($transaction);

        $this->assertCount(1, $matches);
        $this->assertSame($invoice->id, $matches->first()->invoice_id);
        $this->assertSame(MatchConfidence::AmountAndCustomer->value, $matches->first()->confidence);
        $this->assertDatabaseMissing('bank_matches', [
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $oldInvoice->id,
            'is_confirmed' => false,
        ]);
    }

    public function test_debit_transactions_do_not_create_matches(): void
    {
        $service = app(MatchingService::class);
        $customer = $this->createCustomer('No Match AG');

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2026-020',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'total' => 100.00,
            'currency' => 'CHF',
        ]);

        $transaction = $this->createTransaction([
            'type' => BankTransactionType::Debit,
            'amount' => 100.00,
            'debtor_name' => 'No Match AG',
        ]);

        $matches = $service->findAndStoreMatches($transaction);

        $this->assertTrue($matches->isEmpty());
        $this->assertDatabaseCount('bank_matches', 0);
    }

    private function createCustomer(string $name): Customer
    {
        return Customer::create([
            'organization_id' => $this->organization->id,
            'name' => $name,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTransaction(array $overrides = []): BankTransaction
    {
        return BankTransaction::create(array_merge([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-20',
            'description' => 'Incoming payment',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'TX-1',
        ], $overrides));
    }
}