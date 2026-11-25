<?php

namespace App\Support\Contracts;

interface ArchiveCustodyEnrollment
{
    /** Return the operator-recorded agreement enrollment UUID for new captures. */
    public function activeId(string $organizationId): ?string;
}
