<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class OrganizationCommunicationsTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_organization_can_save_a_reply_to_address(): void
    {
        $this->actAsOrg()
            ->put(route('settings.communications'), [
                'contact_email' => 'billing@example.ch',
                'invoice_email_subject' => 'Invoice {invoice_number}',
                'invoice_email_body' => 'Please find your invoice attached.',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('organizations', [
            'id' => $this->organization->id,
            'contact_email' => 'billing@example.ch',
        ]);
    }

    public function test_invalid_reply_to_address_is_rejected(): void
    {
        $this->actAsOrg()
            ->put(route('settings.communications'), [
                'contact_email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('contact_email');

    }
}
