<?php

namespace App\Domains\Accounting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A frozen accounting snapshot; prepared does not mean independently sealed.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $fiscal_year_id
 * @property int $version
 * @property string $state
 * @property string $provenance
 * @property bool $archival_pdf
 * @property array<string, mixed> $snapshot
 * @property string $snapshot_sha256
 * @property string $package_path
 * @property string|null $manifest_sha256
 * @property Carbon $captured_at
 * @property Carbon $retention_until
 * @property Carbon|null $prepared_at
 * @property Carbon|null $verified_at
 * @property bool $legal_hold
 * @property string|null $failure
 */
class ArchivePackage extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $guarded = [];

    protected $attributes = ['state' => 'pending', 'version' => 1, 'provenance' => 'native', 'archival_pdf' => false];

    protected $hidden = ['snapshot'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array', 'version' => 'integer', 'archival_pdf' => 'boolean',
            'retention_until' => 'datetime', 'captured_at' => 'datetime', 'prepared_at' => 'datetime',
            'verified_at' => 'datetime', 'legal_hold' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $package): void {
            foreach (['organization_id', 'fiscal_year_id', 'version', 'provenance', 'snapshot', 'snapshot_sha256', 'captured_at', 'archival_pdf', 'package_path'] as $field) {
                if ($package->isDirty($field)) {
                    throw new \LogicException('An archive snapshot cannot be replaced. Create a new package version.');
                }
            }
            if ($package->isDirty('retention_until') && $package->retention_until->lt($package->getOriginal('retention_until'))) {
                throw new \LogicException('Archive retention cannot be shortened.');
            }
            if ($package->getOriginal('manifest_sha256') !== null && $package->isDirty('manifest_sha256')) {
                throw new \LogicException('An archive manifest cannot be replaced.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Archive packages require a separately authorized retention disposal process.');
        });
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
