<?php

namespace Tests\Feature;

use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\CreateSupplierData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\DTOs\UpdateSupplierData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::create(['name' => 'Test GmbH', 'currency' => 'CHF']);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);

        app()->instance('current_organization', $this->org);
    }

    // ──────────────────────────────────────────────────────────────
    //  Customer CRUD
    // ──────────────────────────────────────────────────────────────

    public function test_create_customer_persists_record(): void
    {
        $customer = Customer::create((new CreateCustomerData(
            organizationId: $this->org->id,
            name: 'Acme AG',
            email: 'billing@acme.ch',
            country: 'CH',
            currency: 'CHF',
        ))->toArray());

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->org->id,
            'name' => 'Acme AG',
            'email' => 'billing@acme.ch',
        ]);
    }

    public function test_update_customer_changes_fields(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $customer->update((new UpdateCustomerData(
            name: 'New Name',
            city: 'Zurich',
        ))->toArray());
        $updated = $customer->fresh();

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('Zurich', $updated->city);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
    }

    public function test_customer_soft_delete(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'To Delete',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertNull(Customer::find($customer->id));
        $this->assertNotNull(Customer::withTrashed()->find($customer->id));
    }

    public function test_customer_belongs_to_correct_organization(): void
    {
        $customer = Customer::create([
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
        $supplier = Supplier::create((new CreateSupplierData(
            organizationId: $this->org->id,
            name: 'Swisscom AG',
            email: 'invoice@swisscom.ch',
            country: 'CH',
            currency: 'CHF',
            defaultExpenseCategory: 'utilities',
            iban: 'CH56 0483 5012 3456 7800 9',
        ))->toArray());

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $this->org->id,
            'name' => 'Swisscom AG',
            'default_expense_category' => 'utilities',
        ]);
    }

    public function test_update_supplier_changes_category(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Migros',
            'country' => 'CH',
            'currency' => 'CHF',
            'default_expense_category' => 'office',
        ]);

        $supplier->update((new UpdateSupplierData(
            name: $supplier->name,
            defaultExpenseCategory: 'other',
        ))->toArray());
        $updated = $supplier->fresh();

        $this->assertEquals('other', $updated->default_expense_category);
    }

    public function test_supplier_soft_delete(): void
    {
        $supplier = Supplier::create([
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

        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'My Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        Customer::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Customer',
            'country' => 'DE',
            'currency' => 'EUR',
        ]);

        // Global scope for current org should only return its own customers
        $customers = Customer::all();
        $this->assertCount(1, $customers);
        $this->assertEquals('My Customer', $customers->first()->name);
    }

    public function test_supplier_global_scope_isolates_by_organization(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'EUR']);

        Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'My Supplier',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        Supplier::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Supplier',
            'country' => 'DE',
            'currency' => 'EUR',
        ]);

        $suppliers = Supplier::all();
        $this->assertCount(1, $suppliers);
        $this->assertEquals('My Supplier', $suppliers->first()->name);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP Endpoints
    // ──────────────────────────────────────────────────────────────

    public function test_customer_index_returns_inertia_response(): void
    {
        $this->actingAs($this->user);
        $this->setCurrentOrgMiddleware();

        $response = $this->get('/customers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Contacts/Customers/Index'));
    }

    public function test_customer_store_creates_record_and_redirects(): void
    {
        $this->actingAs($this->user);
        $this->setCurrentOrgMiddleware();

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
        $this->setCurrentOrgMiddleware();

        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'To HTTP Delete',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $response = $this->delete("/customers/{$customer->id}");

        $response->assertRedirect('/customers');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_supplier_store_creates_record_and_redirects(): void
    {
        $this->actingAs($this->user);
        $this->setCurrentOrgMiddleware();

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

    private function setCurrentOrgMiddleware(): void
    {
        app()->instance('current_organization', $this->org);
    }
}
