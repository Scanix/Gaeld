<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for journal entry operations.
 */
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
        if ($entry->archived_at !== null) {
            return false;
        }

        return $this->belongsToOrganization($user, $entry)
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $entry->is_posted;
    }

    public function addAttachment(User $user, JournalEntry $entry): bool
    {
        if ($entry->archived_at !== null || ! $this->view($user, $entry)
            || ! $user->hasPermissionTo(Permission::AccountingEdit)) {
            return false;
        }

        $year = $entry->attachmentFiscalYear();
        if ($year !== null) {
            return $year->locked_at === null && in_array($year->status, [FiscalYearStatus::Operative, FiscalYearStatus::Expired], true);
        }

        return ! $entry->organization->isFiscalYearClosed($entry->date->year)
            && ! $entry->organization->fiscalYears()->exists();
    }

    public function withdrawAttachment(User $user, JournalEntry $entry): bool
    {
        return ! $entry->is_posted && $this->addAttachment($user, $entry);
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        if ($entry->archived_at !== null) {
            return false;
        }

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
