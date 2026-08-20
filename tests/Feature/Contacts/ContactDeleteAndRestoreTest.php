<?php

namespace Tests\Feature\Contacts;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactDeleteAndRestoreTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_it_deletes_a_contact_with_no_history(): void
    {
        $contact = Contact::create(['organization_id' => $this->org->id, 'name' => 'Acme AG']);

        $response = $this->actAsOrg()->delete("/contacts/{$contact->uuid}");

        $response->assertRedirect('/contacts');
        $this->assertSoftDeleted($contact);
    }

    public function test_it_refuses_to_delete_a_contact_with_invoices(): void
    {
        $contact = Contact::create(['organization_id' => $this->org->id, 'name' => 'Acme AG']);
        Invoice::factory()->for($this->organization)->create(['customer_id' => $contact->id]);

        $response = $this->actAsOrg()
            ->from('/contacts')
            ->delete("/contacts/{$contact->uuid}");

        $response->assertRedirect('/contacts');
        $response->assertSessionHas('error');
        $this->assertNull($contact->fresh()->deleted_at);
    }

    public function test_it_refuses_to_delete_a_contact_with_expenses(): void
    {
        $contact = Contact::create(['organization_id' => $this->org->id, 'name' => 'Acme AG']);
        Expense::factory()->for($this->organization)->create(['supplier_id' => $contact->id]);

        $response = $this->actAsOrg()->delete("/contacts/{$contact->uuid}");

        $response->assertSessionHas('error');
        $this->assertNull($contact->fresh()->deleted_at);
    }

    public function test_it_restores_a_deleted_contact(): void
    {
        $contact = Contact::create(['organization_id' => $this->org->id, 'name' => 'Acme AG']);
        $contact->delete();

        $response = $this->actAsOrg()->post("/contacts/{$contact->uuid}/restore");

        $response->assertRedirect("/contacts/{$contact->uuid}");
        $this->assertNull($contact->fresh()->deleted_at);
    }

    public function test_trashed_contacts_view_lists_deleted_contacts(): void
    {
        $contact = Contact::create(['organization_id' => $this->org->id, 'name' => 'Deleted Corp']);
        $contact->delete();
        Contact::create(['organization_id' => $this->org->id, 'name' => 'Still Active AG']);

        $response = $this->actAsOrg()->get('/contacts/trashed');

        $response->assertInertia(fn ($page) => $page
            ->where('contacts.data.0.name', 'Deleted Corp')
            ->where('contacts.total', 1)
        );
    }

    public function test_cannot_restore_another_organizations_contact(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignContact = Contact::create(['organization_id' => $otherOrg->id, 'name' => 'Other Org Contact']);
        $foreignContact->delete();

        $response = $this->actAsOrg()->post("/contacts/{$foreignContact->uuid}/restore");

        $response->assertNotFound();
    }
}
