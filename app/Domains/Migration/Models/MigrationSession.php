<?php

namespace App\Domains\Migration\Models;

use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks a migration import session for an organization.
 *
 * Stores the overall status, per-data-type progress, imported counts,
 * and any errors encountered during the import pipeline.
 *
 * @property string $id
 * @property string $organization_id
 * @property Platform $platform
 * @property ImportStatus $status
 * @property array<string, string> $data_types_status Per-type ImportStatus values
 * @property array<string, int> $imported_counts Per-type imported row counts
 * @property array<string, string[]> $imported_record_ids Per-type created record IDs
 * @property array<string, string[]> $errors Per-type error arrays
 * @property int $created_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MigrationSession extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'platform',
        'status',
        'data_types_status',
        'imported_counts',
        'imported_record_ids',
        'errors',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ImportStatus::class,
            'data_types_status' => 'array',
            'imported_counts' => 'array',
            'imported_record_ids' => 'array',
            'errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updateDataTypeStatus(string $dataType, ImportStatus $status): void
    {
        $statuses = $this->data_types_status ?? [];
        $statuses[$dataType] = $status->value;
        $this->update(['data_types_status' => $statuses]);
    }

    public function incrementImportedCount(string $dataType, int $count): void
    {
        $counts = $this->imported_counts ?? [];
        $counts[$dataType] = ($counts[$dataType] ?? 0) + $count;
        $this->update(['imported_counts' => $counts]);
    }

    public function addErrors(string $dataType, array $errors): void
    {
        $allErrors = $this->errors ?? [];
        $allErrors[$dataType] = array_merge($allErrors[$dataType] ?? [], $errors);
        $this->update(['errors' => $allErrors]);
    }

    public function addImportedRecordIds(string $dataType, array $ids): void
    {
        $allIds = $this->imported_record_ids ?? [];
        $allIds[$dataType] = array_merge($allIds[$dataType] ?? [], $ids);
        $this->update(['imported_record_ids' => $allIds]);
    }

    public function canBeReversed(): bool
    {
        return in_array($this->status, [
            ImportStatus::Completed,
            ImportStatus::PartiallyCompleted,
        ], true) && ! empty($this->imported_record_ids);
    }

    public function markCompleted(): void
    {
        $statuses = $this->data_types_status ?? [];
        $hasFailed = in_array(ImportStatus::Failed->value, $statuses, true);
        $hasCompleted = in_array(ImportStatus::Completed->value, $statuses, true);

        $overallStatus = match (true) {
            $hasFailed && $hasCompleted => ImportStatus::PartiallyCompleted,
            $hasFailed => ImportStatus::Failed,
            default => ImportStatus::Completed,
        };

        $this->update([
            'status' => $overallStatus,
            'completed_at' => now(),
        ]);
    }
}
