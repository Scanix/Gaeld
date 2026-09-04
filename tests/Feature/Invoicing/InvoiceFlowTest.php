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
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Contact $customer;

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

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, array<string, mixed>>  $lines
     */
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
            taxTreatment: InvoiceTaxTreatment::tryFrom($data['tax_treatment'] ?? 'standard')
                ?? InvoiceTaxTreatment::Standard,
        ));
    }

    public function test_reverse_charge_requires_an_eu_vat_customer_and_excludes_swiss_vat(): void
    {
        $this->customer->update([
            'country' => 'DE',
            'vat_number' => 'DE123456789',
        ]);

        $invoice = $this->createInvoice(['tax_treatment' => 'reverse_charge']);

        $this->assertSame(InvoiceTaxTreatment::ReverseCharge, $invoice->tax_treatment);
        $this->assertSame('0.00', $invoice->vat_amount);
        $this->assertNull($invoice->lines->first()->vat_rate_id);
        $this->assertSame('1500.00', $invoice->total);

        $posted = app(FinalizeInvoiceAction::class)->execute($invoice);

        $this->assertTrue($posted->journalEntry->isBalanced());
        $this->assertFalse($posted->journalEntry->lines->load('account')->contains(
            fn ($line) => $line->account?->code === '2200',
        ));
        $this->assertDatabaseCount('vat_entries', 0);
    }

    public function test_standard_invoice_can_have_no_vat_when_each_line_has_no_rate(): void
    {
        $invoice = $this->createInvoice(lines: [[
            'description' => 'VAT-exempt service',
            'quantity' => 1,
            'unit_price' => 150.00,
            'vat_rate_id' => '',
        ]]);

        $this->assertSame(InvoiceTaxTreatment::Standard, $invoice->tax_treatment);
        $this->assertNull($invoice->lines->first()->vat_rate_id);
        $this->assertSame('0.00', $invoice->vat_amount);
        $this->assertSame('150.00', $invoice->total);
    }

    public function test_reverse_charge_rejects_a_non_eu_customer_or_missing_vat_number(): void
    {
        $this->customer->update(['country' => 'CH', 'vat_number' => null]);

        $this->expectException(ValidationException::class);

        $this->createInvoice(['tax_treatment' => 'reverse_charge']);
    }

    public function test_duplicate_preserves_reverse_charge_treatment(): void
    {
        $this->customer->update([
            'country' => 'DE',
            'vat_number' => 'DE123456789',
        ]);

        $invoice = $this->createInvoice(['tax_treatment' => 'reverse_charge']);
        $duplicate = app(DuplicateInvoiceAction::class)->execute($invoice);

        $this->assertSame(InvoiceTaxTreatment::ReverseCharge, $duplicate->tax_treatment);
        $this->assertSame('0.00', $duplicate->vat_amount);
        $this->assertNull($duplicate->lines->first()->vat_rate_id);
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

    public function test_invoice_keeps_customer_snapshot_after_contact_address_changes(): void
    {
        $this->customer->update([
            'address' => 'Old Street 1',
            'postal_code' => '1000',
            'city' => 'Lausanne',
            'country' => 'CH',
        ]);

        $invoice = $this->createInvoice();

        $this->customer->update([
            'address' => 'New Street 9',
            'postal_code' => '8000',
            'city' => 'Zürich',
        ]);

        $invoice->refresh();

        $this->assertSame('Old Street 1', $invoice->customer_snapshot['address']);
        $this->assertSame('1000', $invoice->customer_snapshot['postal_code']);
        $this->assertSame('Lausanne', $invoice->customer_snapshot['city']);
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

    public function test_updating_invoice_with_duplicate_number_returns_validation_error(): void
    {
        $this->createInvoice(['number' => 'INV-2026-001']);
        $invoice2 = $this->createInvoice(['number' => 'INV-2026-002']);

        $payload = [
            'number' => 'INV-2026-001', // already taken by invoice1
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'currency' => 'CHF',
            'lines' => [[
                'description' => 'Service',
                'quantity' => 1,
                'unit_price' => 100.00,
                'vat_rate_id' => $this->vatRate->id,
            ]],
        ];

        $response = $this->actAsOrg()
            ->put(route('invoices.update', $invoice2), $payload);

        $response->assertSessionHasErrors('number');
    }

    public function test_updating_invoice_keeping_its_own_number_succeeds(): void
    {
        $invoice = $this->createInvoice(['number' => 'INV-2026-001']);

        $payload = [
            'number' => 'INV-2026-001', // same invoice, should not conflict with itself
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'currency' => 'CHF',
            'lines' => [[
                'description' => 'Updated service',
                'quantity' => 2,
                'unit_price' => 200.00,
                'vat_rate_id' => $this->vatRate->id,
            ]],
        ];

        $response = $this->actAsOrg()
            ->put(route('invoices.update', $invoice), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('invoices.show', $invoice));
    }

    public function test_create_invoice_regenerates_number_on_unique_collision(): void
    {
        // Simulate a race where the suggested number was already taken by a
        // concurrent insert between the form render and the store request.
        $this->createInvoice(['number' => 'INV-'.now()->year.'-001']);

        $second = $this->createInvoice(['number' => 'INV-'.now()->year.'-001']);

        $this->assertSame('INV-'.now()->year.'-002', $second->number);
        $this->assertSame(2, Invoice::where('organization_id', $this->org->id)->count());
    }

    public function test_create_invoice_does_not_rewrite_custom_number_on_collision(): void
    {
        $this->createInvoice(['number' => 'CUSTOM-ABC']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createInvoice(['number' => 'CUSTOM-ABC']);
    }
}
