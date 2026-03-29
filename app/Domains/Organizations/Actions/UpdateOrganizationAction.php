<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;

/**
 * Applies validated changes to an existing organization.
 */
class UpdateOrganizationAction
{
    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function execute(Organization $organization, UpdateOrganizationData $data): Organization
    {
        return $this->organizationService->update($organization, $data);
    }
}
