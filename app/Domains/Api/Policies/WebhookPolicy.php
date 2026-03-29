<?php

namespace App\Domains\Api\Policies;

use App\Domains\Api\Models\Webhook;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for webhook endpoint management.
 */
class WebhookPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $this->belongsToOrganization($user, $webhook)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $this->belongsToOrganization($user, $webhook)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->belongsToOrganization($user, $webhook)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function regenerateSecret(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }
}
