<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;

/**
 * Creates a new organization and attaches the requesting user as owner.
 */
class CreateOrganizationAction
{
    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function execute(User $owner, CreateOrganizationData $data): Organization
    {
        return $this->organizationService->create($owner, $data);
    }
}
