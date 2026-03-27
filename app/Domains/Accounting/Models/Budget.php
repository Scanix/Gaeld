<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organization_id
 * @property int $account_id
 * @property int $fiscal_year
 * @property string $monthly_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Budget extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'account_id',
        'fiscal_year',
        'monthly_amount',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'monthly_amount' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('fiscal_year', $year);
    }
}
