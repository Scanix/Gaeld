<?php

namespace Tests\Feature\Contacts;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactPersonRouteTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_contact_person_can_be_created_edited_and_removed_from_the_public_contact_url(): void
    {
        $contact = Contact::factory()->for($this->organization)->create();
        $url = "/contacts/{$contact->uuid}/contact-persons";

        $person = $this->actAsOrg()->postJson($url, [
            'first_name' => 'Lea',
            'last_name' => 'Meyer',
        ])->assertCreated()->json('contact_person.id');

        $this->putJson("$url/$person", [
            'first_name' => 'Lea',
            'last_name' => 'Meier',
        ])->assertOk()->assertJsonPath('contact_person.last_name', 'Meier');
        $this->deleteJson("$url/$person")->assertNoContent();
        $this->assertDatabaseMissing('contact_persons', ['id' => $person]);
    }

    public function test_legacy_numeric_contact_urls_still_resolve(): void
    {
        $contact = Contact::factory()->for($this->organization)->create();
        $this->actAsOrg()->postJson("/contacts/00{$contact->id}/contact-persons", [
            'first_name' => 'Lea',
            'last_name' => 'Meyer',
        ])->assertCreated();
    }

    public function test_the_parent_contact_must_be_active_in_the_current_organization(): void
    {
        $archived = Contact::factory()->for($this->organization)->create();
        $archived->delete();
        $other = Contact::factory()->for(Organization::factory()->create())->create();
        $this->actAsOrg();

        foreach ([$archived->uuid, $archived->id, $other->uuid, $other->id, 'missing', '0', '999999999999999999999999'] as $parent) {
            $this->postJson("/contacts/$parent/contact-persons", [
                'first_name' => 'Lea',
                'last_name' => 'Meyer',
            ])->assertNotFound();
        }
        $this->assertDatabaseEmpty('contact_persons');
    }
}
