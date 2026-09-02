<?php

namespace App\Domains\Organizations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracked customer document bytes for one organization.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $bytes_used
 */
class OrganizationDocumentStorageUsage extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'bytes_used',
    ];

    protected $casts = [
        'bytes_used' => 'integer',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
