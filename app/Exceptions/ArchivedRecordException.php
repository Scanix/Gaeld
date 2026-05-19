<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a legally archived record is mutated or deleted.
 *
 * Enforces Swiss CO 10-year immutability at the model layer as a safety net
 * for code paths that bypass policy authorization (jobs, console commands,
 * raw ->save() calls).
 */
class ArchivedRecordException extends RuntimeException
{
    public static function locked(string $documentType, string $id): self
    {
        return new self(__('app.archived_record_locked', [
            'type' => $documentType,
            'id' => $id,
        ]));
    }
}
