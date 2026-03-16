<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Users\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->organizations()->where('organizations.id', $entry->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $entry->is_posted;
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $entry->is_posted;
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $entry->is_posted;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->view($user, $entry) && $entry->is_posted;
    }
}
