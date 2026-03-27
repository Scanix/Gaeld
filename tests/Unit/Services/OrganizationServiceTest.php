<?php

namespace Tests\Unit\Services;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;
use App\Support\AddressData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_attaches_owner_and_persists_address_fields(): void
    {
        $service = new OrganizationService;
        $owner = User::factory()->create();
        /** @var User $owner */
        $organization = $service->create($owner, new CreateOrganizationData(
            name: 'Service Org',
            legalName: 'Service Org SA',
            addressData: new AddressData(
                address: 'Lake Road 1',
                city: 'Zurich',
                postalCode: '8000',
                country: 'CH',
                canton: 'ZH',
            ),
            country: 'CH',
            vatNumber: 'CHE-123.123.123',
            currency: 'CHF',
            fiscalYearStart: '01-01',
            locale: 'en',
        ));

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Service Org',
            'city' => 'Zurich',
            'canton' => 'ZH',
        ]);
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_update_persists_new_details(): void
    {
        $service = new OrganizationService;
        $organization = Organization::create([
            'name' => 'Before Update',
            'currency' => 'CHF',
            'locale' => 'en',
        ]);

        $updated = $service->update($organization, new UpdateOrganizationData(
            name: 'After Update',
            legalName: 'After Update AG',
            addressData: new AddressData(
                address: 'Updated Street 4',
                city: 'Basel',
                postalCode: '4000',
                country: 'CH',
                canton: 'BS',
            ),
            vatNumber: 'CHE-555.444.333',
            currency: 'EUR',
            locale: 'fr',
        ));

        $this->assertSame($organization->id, $updated->id);
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'After Update',
            'city' => 'Basel',
            'currency' => 'EUR',
            'locale' => 'fr',
        ]);
    }

    public function test_add_and_remove_member_manage_pivot_membership(): void
    {
        $service = new OrganizationService;
        $organization = Organization::create([
            'name' => 'Membership Org',
            'currency' => 'CHF',
        ]);
        $user = User::factory()->create();
        /** @var User $user */
        $service->addMember($organization, $user, 'member');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $service->removeMember($organization, $user);

        $this->assertDatabaseMissing('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
    }
}
