<?php

namespace Tests\Feature\Contacts;

use App\Domains\Contacts\DTOs\CreateContactData;
use App\Domains\Contacts\DTOs\UpdateContactData;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Models\Organization;
use App\Support\AddressData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactsFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  Customer CRUD
    // ──────────────────────────────────────────────────────────────

    public function test_create_customer_persists_record(): void
    {
        $customer = Contact::create((new CreateContactData(
            organizationId: $this->org->id,
            name: 'Acme AG',
            addressData: new AddressData(country: 'CH'),
            email: 'billing@acme.ch',
            currency: 'CHF',
        ))->toArray());

        $this->assertInstanceOf(Contact::class, $customer);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->org->id,
            'name' => 'Acme AG',
            'email' => 'billing@acme.ch',
        ]);
    }

    public function test_update_customer_changes_fields(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $customer->update((new UpdateContactData(
            name: 'New Name',
            addressData: new AddressData(city: 'Zurich'),
        ))->toArray());
        $updated = $customer->fresh();

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('Zurich', $updated->city);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
    }

    public function test_customer_soft_delete(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'To Delete',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertNull(Contact::find($customer->id));
        $this->assertNotNull(Contact::withTrashed()->find($customer->id));
    }

    public function test_customer_belongs_to_correct_organization(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Tenant Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->assertEquals($this->org->id, $customer->organization_id);
        $this->assertInstanceOf(Organization::class, $customer->organization);
    }

    // ──────────────────────────────────────────────────────────────
    //  Supplier CRUD
    // ──────────────────────────────────────────────────────────────

    public function test_create_supplier_persists_record(): void
    {
        $supplier = Contact::create((new CreateContactData(
            organizationId: $this->org->id,
            name: 'Swisscom AG',
            addressData: new AddressData(country: 'CH'),
            email: 'invoice@swisscom.ch',
            currency: 'CHF',
            defaultExpenseCategory: 'utilities',
            iban: 'CH56 0483 5012 3456 7800 9',
        ))->toArray());

        $this->assertInstanceOf(Contact::class, $supplier);
        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $this->org->id,
            'name' => 'Swisscom AG',
            'default_expense_category' => 'utilities',
        ]);
    }

    public function test_update_supplier_changes_category(): void
    {
        $supplier = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Migros',
            'country' => 'CH',
            'currency' => 'CHF',
            'default_expense_category' => 'office',
        ]);

        $supplier->update((new UpdateContactData(
            name: $supplier->name,
            defaultExpenseCategory: 'other',
        ))->toArray());
        $updated = $supplier->fresh();

        $this->assertEquals('other', $updated->default_expense_category);
    }

    public function test_supplier_soft_delete(): void
    {
        $supplier = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'To Delete Supplier',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $supplier->delete();

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Tenant Isolation
    // ──────────────────────────────────────────────────────────────

    public function test_customer_global_scope_isolates_by_organization(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'EUR']);

        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'My Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        Contact::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Customer',
            'country' => 'DE',
            'currency' => 'EUR',
        ]);

        // Global scope for current org should only return its own customers
        $customers = Contact::all();
        $this->assertCount(1, $customers);
        $this->assertEquals('My Customer', $customers->first()->name);
    }

    public function test_supplier_global_scope_isolates_by_organization(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'EUR']);

        Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'My Supplier',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        Contact::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Supplier',
            'country' => 'DE',
            'currency' => 'EUR',
        ]);

        $suppliers = Contact::all();
        $this->assertCount(1, $suppliers);
        $this->assertEquals('My Supplier', $suppliers->first()->name);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP Endpoints
    // ──────────────────────────────────────────────────────────────

    public function test_customer_index_returns_inertia_response(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/customers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Contacts/Customers/Index'));
    }

    public function test_customer_store_creates_record_and_redirects(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/customers', [
            'name' => 'HTTP Customer',
            'email' => 'http@test.ch',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('customers', ['name' => 'HTTP Customer']);
    }

    public function test_customer_destroy_soft_deletes(): void
    {
        $this->actingAs($this->user);

        $customer = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'To HTTP Delete',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $response = $this->delete("/customers/{$customer->uuid}");

        $response->assertRedirect('/customers');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_supplier_store_creates_record_and_redirects(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/suppliers', [
            'name' => 'HTTP Supplier',
            'email' => 'http@supplier.ch',
            'country' => 'CH',
            'currency' => 'CHF',
            'default_expense_category' => 'software',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('suppliers', ['name' => 'HTTP Supplier']);
    }
}
