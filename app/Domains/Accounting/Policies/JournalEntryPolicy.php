<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class JournalEntryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $entry->is_posted;
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingDelete)
            && ! $entry->is_posted;
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $entry->is_posted;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && $entry->is_posted;
    }
}
