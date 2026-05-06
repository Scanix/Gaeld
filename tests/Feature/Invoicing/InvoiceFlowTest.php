<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\CancelInvoiceAction;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DuplicateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Customer $customer;

    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

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

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '2200',
            'name' => 'VAT Output',
            'type' => AccountType::Liability->value,
        ]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $this->customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Client AG',
        ]);
    }

    private function createInvoice(array $overrides = [], array $lines = []): Invoice
    {
        $action = app(CreateInvoiceAction::class);

        $data = array_merge([
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-001',
            'issue_date' => '2026-03-16',
            'due_date' => '2026-04-15',
        ], $overrides);

        return $action->execute(new CreateInvoiceData(
            organizationId: $this->org->id,
            customerId: $data['customer_id'],
            number: $data['number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'],
            currency: $data['currency'] ?? 'CHF',
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            lines: array_map(
                fn (array $l) => InvoiceLineData::fromArray($l),
                $lines ?: [[
                    'description' => 'Web Development',
                    'quantity' => 10,
                    'unit_price' => 150.00,
                    'vat_rate_id' => $this->vatRate->id,
                ]]
            ),
        ));
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
        $accountingService = app(InvoiceAccountingService::class);
        $payment = $accountingService->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $invoice->total,
            paymentDate: '2026-04-01',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

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

        $accountingService = app(InvoiceAccountingService::class);
        $payment1 = $accountingService->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $halfAmount,
            paymentDate: '2026-04-01',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertEquals(1, $invoice->payments()->count());

        // Pay remainder
        $remaining = (float) $invoice->amountDue();
        $payment2 = $accountingService->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $remaining,
            paymentDate: '2026-04-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals(2, $invoice->payments()->count());
        $this->assertTrue($invoice->isFullyPaid());
    }

    public function test_duplicate_invoice(): void
    {
        $invoice = $this->createInvoice();
        $action = app(DuplicateInvoiceAction::class);

        $duplicate = $action->execute($invoice);

        $this->assertEquals(InvoiceStatus::Draft, $duplicate->status);
        $this->assertNotEquals($invoice->id, $duplicate->id);
        $this->assertEquals($invoice->customer_id, $duplicate->customer_id);
        $this->assertEquals($invoice->lines()->count(), $duplicate->lines()->count());
        $this->assertNull($duplicate->journal_entry_id);
    }

    public function test_cancel_finalized_invoice_reverses_journal_entry(): void
    {
        // 1. Create and finalize
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);

        $originalRef = $invoice->journalEntry->reference;

        // 2. Cancel — should reverse the journal entry
        $invoice = app(CancelInvoiceAction::class)->execute($invoice);

        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->status);

        // 3. Verify reversal entry exists
        $reversal = JournalEntry::where('organization_id', $this->org->id)
            ->where('reference', 'REV-'.$originalRef)
            ->first();

        $this->assertNotNull($reversal, 'Reversal journal entry should exist');
        $this->assertTrue($reversal->isBalanced());

        // 4. Verify AR balance nets to zero
        $ar = Account::where('organization_id', $this->org->id)->where('code', '1100')->first();
        $ledgerQuery = app(LedgerQueryService::class);
        $arBalance = $ledgerQuery->accountBalance($ar->id);
        $this->assertEquals('0.00', number_format((float) $arBalance, 2, '.', ''));
    }
}
