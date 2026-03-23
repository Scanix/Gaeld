<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\Organization;

/**
 * Holds the current organization for the request lifecycle.
 *
 * Set by EnsureHasOrganization middleware, injected into controllers
 * via constructor or method injection.
 */
class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): Organization
    {
        if (! $this->organization) {
            throw new \RuntimeException('No current organization set. Ensure EnsureHasOrganization middleware is applied.');
        }

        return $this->organization;
    }

    public function id(): string
    {
        return $this->get()->id;
    }

    public function isBound(): bool
    {
        return $this->organization !== null;
    }
}
