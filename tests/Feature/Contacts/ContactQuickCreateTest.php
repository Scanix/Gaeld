<?php

namespace Tests\Feature\Contacts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ContactQuickCreateTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Regression test for the quick-create contact modal bug: the JSON
     * response must be keyed 'contact' (matching ContactController's
     * resourceName()), not 'customer'/'supplier'. QuickCreateContactModal.vue
     * used to read the wrong key and always reported a false save failure.
     */
    public function test_json_post_returns_contact_under_the_contact_key(): void
    {
        $response = $this->actAsOrg()
            ->postJson('/contacts', ['name' => 'Acme AG', 'country' => 'CH', 'currency' => 'CHF']);

        $response->assertCreated();
        $response->assertJsonStructure(['contact' => ['id', 'name']]);
        $response->assertJsonMissingPath('customer');
        $response->assertJsonMissingPath('supplier');
        $this->assertSame('Acme AG', $response->json('contact.name'));
    }
}
