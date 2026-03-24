<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $entry->is_posted;
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingDelete)
            && ! $entry->is_posted;
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $entry->is_posted;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && $entry->is_posted;
    }
}
