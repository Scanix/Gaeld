<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DuplicateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private Customer $customer;
    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Client AG',
        ]);
    }

    private function createInvoice(array $overrides = [], array $lines = []): Invoice
    {
        $action = new CreateInvoiceAction();

        return $action->execute(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-001',
            'issue_date' => '2026-03-16',
            'due_date' => '2026-04-15',
        ], $overrides), $lines ?: [[
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 150.00,
            'vat_rate_id' => $this->vatRate->id,
        ]]);
    }

    public function test_complete_invoice_flow(): void
    {
        // 1. Create invoice
        $invoice = $this->createInvoice();

        $this->assertEquals(InvoiceStatus::Draft, $invoice->status);
        $this->assertEquals('1500.00', $invoice->subtotal);

        // 2. Finalize invoice (posts to ledger)
        $finalizeAction = app(FinalizeInvoiceAction::class);
        $invoice = $finalizeAction->execute($invoice);

        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertTrue($invoice->journalEntry->isBalanced());

        // 3. Record full payment
        $invoiceService = app(InvoiceService::class);
        $payment = $invoiceService->recordPayment($invoice, [
            'amount' => (float) $invoice->total,
            'payment_date' => '2026-04-01',
            'payment_method' => 'bank',
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals((float) $invoice->total, (float) $payment->amount);
        $this->assertNotNull($payment->journal_entry_id);
    }

    public function test_partial_payment_flow(): void
    {
        $invoice = $this->createInvoice();

        $finalizeAction = app(FinalizeInvoiceAction::class);
        $invoice = $finalizeAction->execute($invoice);

        // Pay half
        $total = (float) $invoice->total;
        $halfAmount = round($total / 2, 2);

        $invoiceService = app(InvoiceService::class);
        $payment1 = $invoiceService->recordPayment($invoice, [
            'amount' => $halfAmount,
            'payment_date' => '2026-04-01',
            'payment_method' => 'bank',
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertEquals(1, $invoice->payments()->count());

        // Pay remainder
        $remaining = (float) $invoice->amountDue();
        $payment2 = $invoiceService->recordPayment($invoice, [
            'amount' => $remaining,
            'payment_date' => '2026-04-10',
            'payment_method' => 'bank',
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals(2, $invoice->payments()->count());
        $this->assertTrue($invoice->isFullyPaid());
    }

    public function test_duplicate_invoice(): void
    {
        $invoice = $this->createInvoice();
        $action = new DuplicateInvoiceAction();

        $duplicate = $action->execute($invoice);

        $this->assertEquals(InvoiceStatus::Draft, $duplicate->status);
        $this->assertNotEquals($invoice->id, $duplicate->id);
        $this->assertEquals($invoice->customer_id, $duplicate->customer_id);
        $this->assertEquals($invoice->lines()->count(), $duplicate->lines()->count());
        $this->assertNull($duplicate->journal_entry_id);
    }
}
