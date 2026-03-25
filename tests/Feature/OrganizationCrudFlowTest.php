<?php

namespace Tests\Feature;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class OrganizationCrudFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $owner;

    private User $member;

    private User $outsider;

    private Organization $primaryOrganization;

    private Organization $secondaryOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->primaryOrganization = Organization::create([
            'name' => 'Primary Org',
            'currency' => 'CHF',
        ]);
        $this->secondaryOrganization = Organization::create([
            'name' => 'Secondary Org',
            'currency' => 'EUR',
        ]);

        $this->primaryOrganization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->primaryOrganization, 'owner');
        $this->primaryOrganization->users()->attach($this->member->id, ['role' => 'member']);
        $this->assignOrganizationRole($this->member, $this->primaryOrganization, 'member');
        $this->secondaryOrganization->users()->attach($this->outsider->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->outsider, $this->secondaryOrganization, 'owner');
    }

    public function test_owner_can_view_organization_index_and_only_see_memberships(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get('/organizations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.id', $this->primaryOrganization->id)
            ->where('organizations.0.name', 'Primary Org'));
    }

    public function test_member_can_view_organization_show_but_cannot_view_foreign_org(): void
    {
        $allowed = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get("/organizations/{$this->primaryOrganization->id}");

        $allowed->assertStatus(200);
        $allowed->assertInertia(fn ($page) => $page
            ->component('Organizations/Show')
            ->where('organization.id', $this->primaryOrganization->id));

        $forbidden = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->get("/organizations/{$this->secondaryOrganization->id}");

        $forbidden->assertForbidden();
    }

    public function test_owner_can_create_another_organization_and_is_attached_as_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/organizations', [
                'name' => 'Created Org',
                'legal_name' => 'Created Org SA',
                'address' => 'Rue du Lac 1',
                'city' => 'Geneva',
                'postal_code' => '1200',
                'canton' => 'GE',
                'country' => 'CH',
                'vat_number' => 'CHE-111.222.333',
                'currency' => 'CHF',
                'locale' => 'en',
            ]);

        $organization = Organization::where('name', 'Created Org')->firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization));
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
        $this->assertSame('Created Org SA', $organization->legal_name);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->post('/organizations', [
                'name' => '',
                'country' => 'CHE',
                'currency' => 'TOOLONG',
                'locale' => 'xx',
            ]);

        $response->assertSessionHasErrors(['name', 'country', 'currency', 'locale']);
        $this->assertDatabaseMissing('organizations', ['name' => '']);
    }

    public function test_owner_can_update_organization_and_member_cannot(): void
    {
        $ownerResponse = $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->put("/organizations/{$this->primaryOrganization->id}", [
                'name' => 'Primary Org Updated',
                'legal_name' => 'Primary Org Holdings',
                'address' => 'New Street 5',
                'city' => 'Bern',
                'postal_code' => '3000',
                'canton' => 'BE',
                'country' => 'CH',
                'vat_number' => 'CHE-999.888.777',
                'currency' => 'CHF',
                'locale' => 'de',
            ]);

        $ownerResponse->assertRedirect(route('organizations.show', $this->primaryOrganization));
        $this->assertDatabaseHas('organizations', [
            'id' => $this->primaryOrganization->id,
            'name' => 'Primary Org Updated',
            'city' => 'Bern',
            'country' => 'CH',
            'locale' => 'de',
        ]);

        $memberResponse = $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->primaryOrganization->id])
            ->put("/organizations/{$this->primaryOrganization->id}", [
                'name' => 'Member Attempt',
                'legal_name' => 'Member Attempt',
                'address' => null,
                'city' => null,
                'postal_code' => null,
                'canton' => null,
                'country' => 'CH',
                'vat_number' => null,
                'currency' => 'CHF',
                'locale' => 'en',
            ]);

        $memberResponse->assertForbidden();
        $this->assertDatabaseMissing('organizations', [
            'id' => $this->primaryOrganization->id,
            'name' => 'Member Attempt',
        ]);
    }
}