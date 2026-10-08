<?php

namespace Tests\Feature\Contacts;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactAddressTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_contact_creation_preserves_the_entered_country_name_and_salutation(): void
    {
        $response = $this->actAsOrg()->postJson('/contacts', [
            'type' => 'individual',
            'salutation' => 'Monsieur',
            'name' => 'Maël Bächtold',
            'address' => "Rue de l'Ecluse 66a",
            'postal_code' => '2000',
            'city' => 'Neuchâtel',
            'country' => 'CH',
            'country_name' => 'Suisse',
            'currency' => 'CHF',
        ]);

        $response->assertCreated()
            ->assertJsonPath('contact.salutation', 'Monsieur')
            ->assertJsonPath('contact.country', 'CH')
            ->assertJsonPath('contact.country_name', 'Suisse');

        $contact = Contact::where('uuid', $response->json('contact.uuid'))->firstOrFail();
        $snapshot = $contact->toInvoiceSnapshot();

        $this->assertSame('Monsieur', $snapshot['salutation']);
        $this->assertSame('Suisse', $snapshot['country_name']);
        $this->assertSame('CH', $snapshot['country']);
    }

    public function test_contact_edit_updates_the_country_name_without_changing_the_iso_code(): void
    {
        $contact = Contact::factory()->for($this->organization)->create([
            'name' => 'Maël Bächtold',
            'salutation' => 'Monsieur',
            'country' => 'CH',
            'country_name' => 'Schweiz',
        ]);

        $this->actAsOrg()->put("/contacts/{$contact->uuid}", [
            'name' => 'Maël Bächtold',
            'salutation' => 'M.',
            'country_name' => 'Suisse',
        ])->assertRedirect("/contacts/{$contact->uuid}");

        $contact->refresh();

        $this->assertSame('M.', $contact->salutation);
        $this->assertSame('Suisse', $contact->country_name);
        $this->assertSame('CH', $contact->country);
    }

    public function test_contact_edit_can_clear_the_country_name_and_salutation(): void
    {
        $contact = Contact::factory()->for($this->organization)->create([
            'salutation' => 'Monsieur',
            'country_name' => 'Suisse',
        ]);

        $this->actAsOrg()->put("/contacts/{$contact->uuid}", [
            'name' => $contact->name,
            'salutation' => '',
            'country_name' => '',
        ])->assertRedirect("/contacts/{$contact->uuid}");

        $contact->refresh();

        $this->assertNull($contact->salutation);
        $this->assertNull($contact->country_name);
        $this->assertSame('CH', $contact->country);
    }

    public function test_omitting_the_country_name_and_salutation_preserves_existing_values(): void
    {
        $contact = Contact::factory()->for($this->organization)->create([
            'salutation' => 'Monsieur',
            'country_name' => 'Suisse',
        ]);

        $this->actAsOrg()->put("/contacts/{$contact->uuid}", [
            'name' => 'Updated contact',
        ])->assertRedirect("/contacts/{$contact->uuid}");

        $contact->refresh();

        $this->assertSame('Monsieur', $contact->salutation);
        $this->assertSame('Suisse', $contact->country_name);
    }

    public function test_a_country_name_is_not_inferred_from_the_iso_code(): void
    {
        $contact = Contact::factory()->for($this->organization)->create(['country' => 'CH']);

        $this->assertNull($contact->country_name);
        $this->assertNull($contact->toInvoiceSnapshot()['country_name']);
    }

    public function test_contact_creation_rejects_country_names_and_salutations_over_the_limit(): void
    {
        $this->actAsOrg()->postJson('/contacts', [
            'name' => 'Maël Bächtold',
            'salutation' => str_repeat('x', 51),
            'country_name' => str_repeat('x', 101),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['salutation', 'country_name']);
    }
}
