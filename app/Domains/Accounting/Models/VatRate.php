<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tax rate definition scoped to an organization (e.g. 8.1 % Swiss VAT).
 *
 * Each rate has a short code, an active/inactive flag, and one rate
 * can be marked as the organization default.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $name
 * @property string $rate
 * @property string $code
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VatRate extends Model
{
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'rate',
        'code',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
