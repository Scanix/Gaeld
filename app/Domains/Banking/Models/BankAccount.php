<?php

namespace App\Domains\Banking\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $organization_id
 * @property int|null $account_id
 * @property string $name
 * @property string|null $iban
 * @property string|null $bank_name
 * @property string $currency
 * @property string $balance
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class BankAccount extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'account_id',
        'name',
        'iban',
        'bank_name',
        'currency',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Accounting\Models\Account::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
