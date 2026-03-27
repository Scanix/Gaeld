<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organization_id
 * @property string $document_type
 * @property string $document_id
 * @property int $fiscal_year
 * @property string $checksum_sha256
 * @property string $storage_path
 * @property Carbon $archived_at
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 */
class LegalArchive extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'document_type',
        'document_id',
        'fiscal_year',
        'checksum_sha256',
        'storage_path',
        'archived_at',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'archived_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at->diffInDays(now()) <= 365;
    }
}
