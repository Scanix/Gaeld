<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Chart-of-accounts entry (ledger account) scoped to an organization.
 *
 * Supports a hierarchical parent–child structure for grouping
 * and an active/inactive flag for soft-disabling unused accounts.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property int|null $parent_id
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Account extends Model
{
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => AccountType::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }
}
