<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\TaxDeclarationStatus;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property TaxDeclarationStatus $status
 * @property Carbon|null $locked_at
 * @property int|null $locked_by_user_id
 * @property-read User|null $lockedBy
 */
class TaxDeclaration extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'fiscal_year',
        'canton',
        'status',
        'data',
        'finalized_at',
        'locked_at',
        'locked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'status' => TaxDeclarationStatus::class,
            'data' => 'array',
            'finalized_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === TaxDeclarationStatus::Finalized;
    }
}
