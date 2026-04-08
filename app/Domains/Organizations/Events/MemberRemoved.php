<?php

namespace App\Domains\Organizations\Events;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a member is removed from an organization.
 *
 * Listeners can react to clean up tokens, webhooks, or notify admins.
 */
class MemberRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $user,
    ) {}
}
