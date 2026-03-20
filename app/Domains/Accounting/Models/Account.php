<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property int|null $parent_id
 * @property bool $is_active
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Account extends Model
{
    use BelongsToOrganization, HasFactory;

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
