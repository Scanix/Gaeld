<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

/**
 * Regression coverage for InvoiceApiController::completeUpdatePayload().
 *
 * Previously this took an explicit $organizationId parameter (inconsistent
 * with ExpenseApiController's equivalent helper, which reads it from the
 * model). Standardized to read $invoice->organization_id directly — this
 * test locks in that a sparse PUT update still resolves the correct
 * organization_id and succeeds.
 */
class InvoiceApiUpdateTest extends SecurityTestCase
{
    public function test_sparse_update_resolves_organization_id_from_the_model(): void
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);

        $vatRate = VatRate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Client AG',
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $customer->id,
        ]);
        $invoice->lines()->create([
            'description' => 'Consulting',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'amount' => '100.00',
            'vat_rate_id' => $vatRate->id,
            'vat_amount' => '8.10',
        ]);

        $token = $this->createApiToken($this->ownerA, $this->orgA);

        // Sparse update: only touch the due_date, omit everything else.
        $response = $this->withToken($token)->putJson("/api/v1/invoices/{$invoice->id}", [
            'due_date' => now()->addDays(45)->toDateString(),
        ]);

        $response->assertOk();

        $invoice->refresh();
        $this->assertSame($this->orgA->id, $invoice->organization_id);
        $this->assertSame(now()->addDays(45)->toDateString(), $invoice->due_date->toDateString());
    }
}
