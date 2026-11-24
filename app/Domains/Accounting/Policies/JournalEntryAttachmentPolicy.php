<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\JournalEntryAttachment;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;
use Illuminate\Support\Facades\Gate;

class JournalEntryAttachmentPolicy extends BasePolicy
{
    public function view(User $user, JournalEntryAttachment $attachment): bool
    {
        return $this->canForEntry($user, $attachment, 'view');
    }

    public function delete(User $user, JournalEntryAttachment $attachment): bool
    {
        return $this->canForEntry($user, $attachment, 'withdrawAttachment');
    }

    private function canForEntry(User $user, JournalEntryAttachment $attachment, string $ability): bool
    {
        if ($attachment->trashed() || ! $this->belongsToOrganization($user, $attachment)) {
            return false;
        }

        $entry = $attachment->journalEntry;

        return $entry !== null
            && $entry->organization_id === $attachment->organization_id
            && Gate::forUser($user)->allows($ability, $entry);
    }
}
