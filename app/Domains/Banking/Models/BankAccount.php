<?php

namespace App\Domains\Banking\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Bank account linked to the organization (e.g. current account, savings).
 *
 * Optionally tied to a ledger {@see Account} for automatic journal entries.
 * Supports soft-deletes and audit logging.
 *
 * @property int $id
 * @property string $organization_id
 * @property int|null $account_id
 * @property string $name
 * @property string|null $iban
 * @property string|null $bank_name
 * @property string $currency
 * @property string $balance
 * @property bool $is_active
 * @property bool $is_mixed_use
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class BankAccount extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'account_id',
        'name',
        'iban',
        'bank_name',
        'currency',
        'balance',
        'is_active',
        'is_mixed_use',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_mixed_use' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
