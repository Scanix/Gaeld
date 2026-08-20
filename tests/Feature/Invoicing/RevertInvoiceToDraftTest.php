<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\RevertInvoiceToDraftAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class RevertInvoiceToDraftTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Contact $customer;

    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        Account::create(['organization_id' => $this->org->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $this->customer = Contact::create(['organization_id' => $this->org->id, 'name' => 'Test Client AG']);
    }

    private function createInvoice(): Invoice
    {
        $action = app(CreateInvoiceAction::class);

        return $action->execute(new CreateInvoiceData(
            organizationId: $this->org->id,
            customerId: $this->customer->id,
            number: 'INV-2026-001',
            issueDate: '2026-03-16',
            dueDate: '2026-04-15',
            currency: 'CHF',
            notes: null,
            paymentTerms: null,
            lines: [InvoiceLineData::fromArray([
                'description' => 'Web Development',
                'quantity' => 10,
                'unit_price' => 150.00,
                'vat_rate_id' => $this->vatRate->id,
            ])],
        ));
    }

    public function test_it_reverts_a_sent_invoice_to_draft_and_reverses_the_journal_entry(): void
    {
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        $this->assertEquals(InvoiceStatus::Sent, $invoice->status);
        $originalRef = $invoice->journalEntry->reference;

        $invoice = app(RevertInvoiceToDraftAction::class)->execute($invoice);

        $this->assertEquals(InvoiceStatus::Draft, $invoice->status);

        $reversal = JournalEntry::where('organization_id', $this->org->id)
            ->where('reference', 'REV-'.$originalRef)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertTrue($reversal->is_posted);
    }

    public function test_it_refuses_to_revert_a_paid_invoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        app(InvoiceAccountingService::class)->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $invoice->total,
            paymentDate: '2026-04-01',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);

        $this->expectException(InvalidInvoiceStateException::class);
        app(RevertInvoiceToDraftAction::class)->execute($invoice);
    }

    public function test_it_refuses_to_revert_once_a_payment_is_recorded_even_if_partial(): void
    {
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        app(InvoiceAccountingService::class)->recordPayment($invoice, new RecordPaymentData(
            amount: '100.00',
            paymentDate: '2026-04-01',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $invoice->refresh();

        $this->expectException(InvalidInvoiceStateException::class);
        app(RevertInvoiceToDraftAction::class)->execute($invoice);
    }

    public function test_revert_to_draft_route_reverts_the_invoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        $response = $this->actAsOrg()->post("/invoices/{$invoice->id}/revert-to-draft");

        $response->assertRedirect(route('invoices.edit', $invoice));
        $this->assertEquals(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_revert_to_draft_route_rejects_a_paid_invoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        app(InvoiceAccountingService::class)->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $invoice->total,
            paymentDate: '2026-04-01',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        $response = $this->actAsOrg()->post("/invoices/{$invoice->id}/revert-to-draft");

        $response->assertForbidden();
    }
}
