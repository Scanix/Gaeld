<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Database\Factories\Domains\Accounting\Models\FiscalYearFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A fiscal accounting period scoped to an organization.
 *
 * Supports custom durations (Swiss "long fiscal year" up to 23 months)
 * and a four-state lifecycle: planned → operative → expired → closed.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property FiscalYearStatus $status
 * @property Carbon|null $locked_at
 * @property string|null $locked_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $lockedBy
 */
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'locked_at',
        'locked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FiscalYearStatus::class,
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

    public function isClosed(): bool
    {
        return $this->status === FiscalYearStatus::Closed;
    }

    public function isOperative(): bool
    {
        return $this->status === FiscalYearStatus::Operative;
    }

    public function isPlanned(): bool
    {
        return $this->status === FiscalYearStatus::Planned;
    }

    /** Whether the given ISO date (YYYY-MM-DD) falls within this fiscal year. */
    public function containsDate(string $date): bool
    {
        $d = Carbon::parse($date)->startOfDay();

        return $d->greaterThanOrEqualTo($this->start_date->startOfDay())
            && $d->lessThanOrEqualTo($this->end_date->startOfDay());
    }

    /** Duration in whole months (rounded down). */
    public function durationInMonths(): int
    {
        return (int) $this->start_date->diffInMonths($this->end_date->copy()->addDay());
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', FiscalYearStatus::Closed->value);
    }

    /** @param Builder<self> $query */
    public function scopeClosed(Builder $query): void
    {
        $query->where('status', FiscalYearStatus::Closed->value);
    }

    /** @param Builder<self> $query */
    public function scopeOperative(Builder $query): void
    {
        $query->where('status', FiscalYearStatus::Operative->value);
    }

    /** @param Builder<self> $query */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
