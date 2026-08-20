<?php

namespace App\Support\Observers;

use App\Exceptions\ArchivedRecordException;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that locks a model once `archived_at` is persisted.
 *
 * Allows the LegalArchivingService to write `archived_at` itself (the
 * column is dirty during that update), then blocks every subsequent
 * update and any delete.
 */
class LocksArchivedRecord
{
    public function __construct(private string $documentType) {}

    public function updating(Model $model): void
    {
        if ($this->isLocked($model) && ! $model->isDirty('archived_at')) {
            throw ArchivedRecordException::locked(
                $this->documentType,
                (string) $model->getKey(),
            );
        }
    }

    public function deleting(Model $model): void
    {
        if ($this->isLocked($model)) {
            throw ArchivedRecordException::locked(
                $this->documentType,
                (string) $model->getKey(),
            );
        }
    }

    private function isLocked(Model $model): bool
    {
        return $model->getOriginal('archived_at') !== null;
    }
}
