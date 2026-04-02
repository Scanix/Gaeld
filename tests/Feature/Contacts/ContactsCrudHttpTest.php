<?php

namespace Tests\Feature\Contacts;

use App\Domains\Contacts\Models\ContactPerson;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactsCrudHttpTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  Customer HTTP tests
    // ──────────────────────────────────────────────────────────────

    public function test_customer_create_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/customers/create')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Contacts/Customers/Create'));
    }

    public function test_customer_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post('/customers', []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_customer_show_loads_relationships(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->actingAs($this->user)
            ->get("/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Customers/Show')
                ->has('customer')
            );
    }

    public function test_customer_edit_page_renders(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Edit Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->actingAs($this->user)
            ->get("/customers/{$customer->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Contacts/Customers/Edit'));
    }

    public function test_customer_update_changes_fields(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->actingAs($this->user)
            ->put("/customers/{$customer->id}", [
                'name' => 'New Name',
                'country' => 'CH',
                'currency' => 'EUR',
                'email' => 'new@example.ch',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'currency' => 'EUR',
        ]);
    }

    public function test_customer_store_returns_json_when_requested(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/customers', [
                'name' => 'JSON Customer',
                'country' => 'CH',
                'currency' => 'CHF',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'JSON Customer']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Supplier HTTP tests
    // ──────────────────────────────────────────────────────────────

    public function test_supplier_index_returns_inertia_response(): void
    {
        $this->actingAs($this->user)
            ->get('/suppliers')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Contacts/Suppliers/Index'));
    }

    public function test_supplier_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post('/suppliers', []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_supplier_update_changes_fields(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Supplier',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->actingAs($this->user)
            ->put("/suppliers/{$supplier->id}", [
                'name' => 'New Supplier',
                'country' => 'DE',
                'currency' => 'EUR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'New Supplier',
        ]);
    }

    public function test_supplier_destroy_soft_deletes(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'To Delete',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->actingAs($this->user)
            ->delete("/suppliers/{$supplier->id}")
            ->assertRedirect('/suppliers');

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    // ──────────────────────────────────────────────────────────────
    //  ContactPerson
    // ──────────────────────────────────────────────────────────────

    public function test_contact_person_belongs_to_customer(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Parent Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $person = ContactPerson::create([
            'contactable_type' => Customer::class,
            'contactable_id' => $customer->id,
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'max@example.ch',
        ]);

        $this->assertInstanceOf(Customer::class, $person->contactable);
        $this->assertEquals($customer->id, $person->contactable_id);
        $this->assertCount(1, $customer->fresh()->contactPersons);
    }

    public function test_contact_person_belongs_to_supplier(): void
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Parent Supplier',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $person = ContactPerson::create([
            'contactable_type' => Supplier::class,
            'contactable_id' => $supplier->id,
            'first_name' => 'Anna',
            'last_name' => 'Klein',
            'email' => 'anna@example.ch',
        ]);

        $this->assertInstanceOf(Supplier::class, $person->contactable);
        $this->assertCount(1, $supplier->fresh()->contactPersons);
    }

    // ──────────────────────────────────────────────────────────────
    //  Authorization
    // ──────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_customers(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $this->get('/suppliers')->assertRedirect('/login');
    }
}
