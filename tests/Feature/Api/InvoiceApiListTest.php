<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

/**
 * Regression coverage for GET /api/v1/invoices.
 *
 * InvoiceResource renders every line through InvoiceLineResource, which reads
 * `$line->vatRate->uuid`. The list query only eager-loaded `lines`, so the
 * first invoice with a line tripped the lazy-loading guard
 * (Model::preventLazyLoading outside production) and the endpoint returned 500.
 */
class InvoiceApiListTest extends SecurityTestCase
{
    public function test_it_lists_invoices_with_vat_rated_lines(): void
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

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

        // Laravel only enforces the lazy-loading guard on models hydrated as
        // part of a multi-model result, so the regression needs at least two.
        $invoices = Invoice::factory()->count(2)->create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $customer->id,
        ]);
        foreach ($invoices as $invoice) {
            $invoice->lines()->create([
                'description' => 'Consulting',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'amount' => '100.00',
                'vat_rate_id' => $vatRate->id,
                'vat_amount' => '8.10',
            ]);
        }

        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $response = $this->withToken($token)->getJson('/api/v1/invoices?filter[status]=draft&sort=-issue_date');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.lines.0.vat_rate_id', $vatRate->uuid)
            ->assertJsonPath('data.1.lines.0.vat_rate_id', $vatRate->uuid);
    }
}
