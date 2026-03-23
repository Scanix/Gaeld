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
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private BankAccount $bankAccount;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Reconciliation Service Org',
            'currency' => 'CHF',
        ]);

        $bank = Account::create([
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

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $bank->id,
            'name' => 'Main Bank',
            'currency' => 'CHF',
            'balance' => 1000.00,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Clamp AG',
        ]);
    }

    public function test_reconcile_with_invoice_clamps_payment_to_outstanding_balance(): void
    {
        $service = app(ReconciliationService::class);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-200',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'total' => 100.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Overpayment received',
            'amount' => 150.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-2026-200',
        ]);

        $result = $service->reconcileWithInvoice($transaction, $invoice);

        $invoice->refresh();

        $this->assertSame('1100.00', $this->bankAccount->fresh()->balance);
        $this->assertSame($invoice->id, $result->matched_invoice_id);
        $this->assertSame('100.00', $invoice->payments()->first()->amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_confirm_match_rejects_duplicate_payment_for_same_transaction_and_invoice(): void
    {
        $service = app(ReconciliationService::class);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-201',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 200.00,
            'vat_amount' => 0,
            'total' => 200.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Duplicate payment attempt',
            'amount' => 200.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-2026-201',
            'matched_invoice_id' => $invoice->id,
        ]);

        $match = BankMatch::create([
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::QrReference->value,
            'match_type' => BankMatchType::QrReference,
            'is_confirmed' => false,
        ]);

        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('This payment has already been recorded for this invoice.');

        $service->confirmMatch($match);
    }
}