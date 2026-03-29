<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;

/**
 * Resolves and switches the active organization for the current user.
 *
 * Used by both web (session-backed) and API (middleware-backed) contexts.
 */
class OrganizationSwitcher
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * Resolve the currently active organization for a user.
     *
     * Priority:
     * 1. API context — CurrentOrganization already bound by EnsureApiOrganization middleware.
     * 2. Web context — organization stored in session.
     * 3. Fallback — first organization the user belongs to.
     */
    public function resolve(User $user): ?Organization
    {
        if ($this->currentOrganization->isBound()) {
            return $this->currentOrganization->get();
        }

        $sessionOrgId = session('current_organization_id');

        if ($sessionOrgId) {
            $org = $user->organizations()->where('organizations.id', $sessionOrgId)->first();
            if ($org) {
                return $org;
            }
            session()->forget('current_organization_id');
        }

        return $user->organizations()->first();
    }

    /**
     * Switch the user's active organization (web context).
     */
    public function switchTo(Organization $organization): void
    {
        session(['current_organization_id' => $organization->id]);
    }
}
