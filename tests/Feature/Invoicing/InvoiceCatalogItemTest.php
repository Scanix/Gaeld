<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class InvoiceCatalogItemTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_it_creates_a_catalog_item(): void
    {
        $vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $response = $this->actAsOrg()->post('/invoices/catalog-items', [
            'name' => 'Consulting (hourly)',
            'description' => 'Standard consulting rate',
            'default_unit_price' => 150,
            'default_vat_rate_id' => $vatRate->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoice_catalog_items', [
            'organization_id' => $this->org->id,
            'name' => 'Consulting (hourly)',
            'default_unit_price' => 150,
            'default_vat_rate_id' => $vatRate->id,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $response = $this->actAsOrg()->post('/invoices/catalog-items', [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_deletes_a_catalog_item(): void
    {
        $item = InvoiceCatalogItem::create([
            'organization_id' => $this->org->id,
            'name' => 'Consulting (hourly)',
            'sort_order' => 1,
        ]);

        $response = $this->actAsOrg()->delete("/invoices/catalog-items/{$item->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('invoice_catalog_items', ['id' => $item->id]);
    }

    public function test_it_scopes_catalog_items_to_the_current_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignItem = InvoiceCatalogItem::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other org item',
            'sort_order' => 1,
        ]);

        $response = $this->actAsOrg()->delete("/invoices/catalog-items/{$foreignItem->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('invoice_catalog_items', ['id' => $foreignItem->id]);
    }
}
