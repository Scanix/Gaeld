<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Organizations\Enums\FiscalYearChangeRequestStatus;
use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FiscalYearChangeRequestStatus $status
 */
class FiscalYearChangeRequest extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'requested_by_user_id',
        'current_start',
        'requested_start',
        'effective_year',
        'status',
        'reason',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FiscalYearChangeRequestStatus::class,
            'effective_year' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === FiscalYearChangeRequestStatus::Pending;
    }
}
