<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * The invoice page must expose server-side authorization for payment and email
 * actions so the UI cannot offer an action the policy would reject.
 */
class InvoiceShowActionsTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Contact $customer;

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

        $this->customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Actions AG',
        ]);
    }

    private function invoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-SHOW-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => '2026-03-16',
            'due_date' => '2026-04-15',
            'status' => InvoiceStatus::Sent,
            'currency' => 'CHF',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
        ], $overrides));
    }

    public function test_issued_invoice_exposes_payment_and_send_actions(): void
    {
        $invoice = $this->invoice();

        $props = $this->actAsOrg()
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['canRecordPayment']);
        $this->assertTrue($props['canSend']);
    }

    public function test_archived_invoice_hides_payment_and_send_actions(): void
    {
        $invoice = $this->invoice(['archived_at' => now()]);

        $props = $this->actAsOrg()
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($props['canRecordPayment']);
        $this->assertFalse($props['canSend']);
    }

    public function test_archived_invoice_rejects_payment_and_send_requests(): void
    {
        $invoice = $this->invoice(['archived_at' => now()]);

        $this->actAsOrg()
            ->post(route('invoices.payment', $invoice), [
                'amount' => '100.00',
                'payment_date' => '2026-04-01',
                'payment_method' => 'bank',
            ])
            ->assertForbidden();

        $this->actAsOrg()
            ->post(route('invoices.send', $invoice))
            ->assertForbidden();
    }

    public function test_draft_invoice_hides_payment_and_send_actions(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Draft]);

        $props = $this->actAsOrg()
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($props['canRecordPayment']);
        $this->assertFalse($props['canSend']);
    }
}
