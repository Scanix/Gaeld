<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Create a new organization and attach the owner.
     */
    public function create(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $org = Organization::create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'canton' => $data['canton'] ?? null,
                'country' => $data['country'] ?? 'CH',
                'vat_number' => $data['vat_number'] ?? null,
                'currency' => $data['currency'] ?? 'CHF',
                'fiscal_year_start' => $data['fiscal_year_start'] ?? '01-01',
                'locale' => $data['locale'] ?? 'en',
            ]);

            $org->users()->attach($owner->id, ['role' => 'owner']);

            return $org;
        });
    }

    /**
     * Add a member to an organization.
     */
    public function addMember(Organization $organization, User $user, string $role = 'member'): void
    {
        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    /**
     * Remove a member from an organization.
     */
    public function removeMember(Organization $organization, User $user): void
    {
        $organization->users()->detach($user->id);
    }
}
