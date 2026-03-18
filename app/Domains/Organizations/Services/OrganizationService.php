<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Create a new organization and attach the owner.
     */
    public function create(User $owner, CreateOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $org = Organization::create([
                'name' => $data->name,
                'legal_name' => $data->legalName ?? $data->name,
                'address' => $data->address,
                'city' => $data->city,
                'postal_code' => $data->postalCode,
                'canton' => $data->canton,
                'country' => $data->country,
                'vat_number' => $data->vatNumber,
                'currency' => $data->currency,
                'fiscal_year_start' => $data->fiscalYearStart,
                'locale' => $data->locale,
            ]);

            $org->users()->attach($owner->id, ['role' => 'owner']);

            return $org;
        });
    }

    public function update(Organization $organization, UpdateOrganizationData $data): Organization
    {
        $organization->update([
            'name' => $data->name,
            'legal_name' => $data->legalName ?? $data->name,
            'address' => $data->address,
            'city' => $data->city,
            'postal_code' => $data->postalCode,
            'canton' => $data->canton,
            'vat_number' => $data->vatNumber,
            'currency' => $data->currency,
            'locale' => $data->locale,
        ]);

        return $organization;
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
