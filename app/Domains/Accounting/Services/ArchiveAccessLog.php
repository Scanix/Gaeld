<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Users\Models\User;

final class ArchiveAccessLog
{
    /** @param array<string, mixed> $metadata */
    public function record(string $orgId, ?string $packageId, string $action, ?User $actor = null, array $metadata = []): ArchiveAccessEvent
    {
        if ($actor !== null) {
            $metadata['actor_identity'] = ['name' => $actor->name, 'email' => $actor->email];
        }

        return ArchiveAccessEvent::create([
            'organization_id' => $orgId, 'archive_package_id' => $packageId,
            'actor_id' => $actor?->id, 'action' => $action, 'metadata' => $metadata, 'occurred_at' => now(),
        ]);
    }
}
