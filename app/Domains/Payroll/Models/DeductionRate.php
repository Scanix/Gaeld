<?php

namespace App\Domains\Payroll\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payroll deduction rate definition (e.g. AVS, LPP, AC) for salary calculations.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $name
 * @property string $code
 * @property string $rate
 * @property string $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeductionRate extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'rate',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
