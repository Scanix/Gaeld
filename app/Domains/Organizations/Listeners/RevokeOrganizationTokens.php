<?php

namespace App\Domains\Organizations\Listeners;

use App\Domains\Organizations\Events\MemberRemoved;

/**
 * Revoke all API tokens (personal and organization-scoped) that belong
 * to the removed user and are associated with the organization.
 */
class RevokeOrganizationTokens
{
    public function handle(MemberRemoved $event): void
    {
        $event->user->tokens()
            ->where('organization_id', $event->organization->id)
            ->delete();
    }
}
