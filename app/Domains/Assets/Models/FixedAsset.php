<?php

namespace App\Domains\Assets\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Capitalized fixed asset tracked for depreciation (e.g. equipment, vehicles).
 *
 * Links to three ledger accounts (asset, depreciation expense, accumulated
 * depreciation) and uses a configurable depreciation method and useful life.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property Carbon $purchase_date
 * @property string $purchase_amount
 * @property int $useful_life_years
 * @property string $salvage_value
 * @property DepreciationMethod $depreciation_method
 * @property int $asset_account_id
 * @property int $depreciation_expense_account_id
 * @property int $accumulated_depreciation_account_id
 * @property Carbon|null $disposed_at
 * @property string|null $disposal_amount
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $status
 * @property-read string $net_book_value
 */
class FixedAsset extends Model
{
    use Auditable, BelongsToOrganization, HasUuids, SoftDeletes;

    protected $appends = ['status', 'net_book_value'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'purchase_date',
        'purchase_amount',
        'useful_life_years',
        'salvage_value',
        'depreciation_method',
        'asset_account_id',
        'depreciation_expense_account_id',
        'accumulated_depreciation_account_id',
        'disposed_at',
        'disposal_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_amount' => 'decimal:2',
            'useful_life_years' => 'integer',
            'salvage_value' => 'decimal:2',
            'depreciation_method' => DepreciationMethod::class,
            'disposed_at' => 'datetime',
            'disposal_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    /** @return HasMany<DepreciationEntry, $this> */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    /** @return HasManyThrough<JournalEntry, DepreciationEntry, $this> */
    public function journalEntries(): HasManyThrough
    {
        return $this->hasManyThrough(
            JournalEntry::class,
            DepreciationEntry::class,
            'fixed_asset_id',
            'id',
            'id',
            'journal_entry_id',
        );
    }

    public function totalDepreciated(): string
    {
        return (string) ($this->depreciationEntries()->sum('amount') ?: '0.00');
    }

    public function netBookValue(): string
    {
        return Money::subtract($this->purchase_amount, $this->totalDepreciated());
    }

    public function getNetBookValueAttribute(): string
    {
        return $this->netBookValue();
    }

    public function isFullyDepreciated(): bool
    {
        return Money::compare($this->netBookValue(), $this->salvage_value) <= 0;
    }

    public function getStatusAttribute(): string
    {
        if ($this->disposed_at) {
            return 'disposed';
        }

        if ($this->isFullyDepreciated()) {
            return 'fully_depreciated';
        }

        return 'active';
    }
}
