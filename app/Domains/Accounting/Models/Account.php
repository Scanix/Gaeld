<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use App\Support\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string $uuid
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property int|null $parent_id
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Account|null $parent
 * @property-read Collection<int, Account> $children
 */
class Account extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organization_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'type' => AccountType::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<TransactionLine, $this> */
    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }
}
