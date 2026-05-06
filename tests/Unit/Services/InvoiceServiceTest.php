<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Invoice Service Org',
            'currency' => 'CHF',
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $this->customer = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Client AG',
        ]);
    }

    public function test_record_payment_uses_default_reference_and_marks_invoice_paid(): void
    {
        $service = app(InvoiceAccountingService::class);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-100',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 400.00,
            'vat_amount' => 0,
            'total' => 400.00,
            'currency' => 'CHF',
        ]);

        $payment = $service->recordPayment($invoice, new RecordPaymentData(
            amount: '400.00',
            paymentDate: '2026-03-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $invoice->refresh();

        $this->assertSame('PAY-INV-2026-100-1', $payment->reference);
        $this->assertTrue($payment->journalEntry->isBalanced());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->amountDue());
    }

    public function test_record_payment_fails_when_bank_account_code_missing(): void
    {
        $service = app(InvoiceAccountingService::class);

        // Delete the bank account so the ledger account cannot be resolved
        Account::where('organization_id', $this->organization->id)
            ->where('code', '1020')
            ->delete();

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-102',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 200.00,
            'vat_amount' => 0,
            'total' => 200.00,
            'currency' => 'CHF',
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service->recordPayment($invoice, new RecordPaymentData(
            amount: '200.00',
            paymentDate: '2026-03-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));
    }

    public function test_record_payment_fails_when_receivable_account_missing(): void
    {
        $service = app(InvoiceAccountingService::class);

        // Delete accounts receivable so the ledger account cannot be resolved
        Account::where('organization_id', $this->organization->id)
            ->where('code', '1100')
            ->delete();

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-103',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 300.00,
            'vat_amount' => 0,
            'total' => 300.00,
            'currency' => 'CHF',
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service->recordPayment($invoice, new RecordPaymentData(
            amount: '300.00',
            paymentDate: '2026-03-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));
    }
}
