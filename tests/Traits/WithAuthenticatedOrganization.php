<?php

namespace Tests\Traits;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;

/**
 * Bootstraps a User + Organization with owner role for Feature tests.
 *
 * Exposes $this->user, $this->organization (and $this->org alias).
 */
trait WithAuthenticatedOrganization
{
    use WithActiveSubscription, WithOrganizationPermissions;

    protected User $user;

    protected Organization $organization;

    protected Organization $org;

    protected function setUpOrganization(array $userAttributes = []): void
    {
        $this->seedPermissions();

        $this->user = User::factory()->create($userAttributes);
        $this->organization = Organization::factory()->create();
        $this->org = $this->organization;

        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->organization, 'owner');

        app(CurrentOrganization::class)->set($this->organization);

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    protected function actAsOrg(): static
    {
        return $this->actingAs($this->user)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }
}
