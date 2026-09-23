<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Expense category scoped to an organization.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $translation_key
 * @property bool $is_default
 * @property bool $is_active
 * @property bool $is_system
 * @property int $sort_order
 * @property int|null $default_expense_account_id
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Account|null $defaultExpenseAccount
 */
class ExpenseCategory extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'translation_key',
        'is_default',
        'is_active',
        'is_system',
        'sort_order',
        'default_expense_account_id',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        return $this->translation_key !== null
            ? __($this->translation_key)
            : $this->name;
    }

    /**
     * Default categories seeded for new organizations.
     */
    public const DEFAULT_CATEGORIES = [
        'Office Supplies',
        'Travel',
        'Software',
        'Professional Services',
        'Marketing',
        'Rent',
        'Utilities',
        'Insurance',
        'Other',
        'Goods Purchased for Resale',
    ];

    public const RESALE_CATEGORY = 'Goods Purchased for Resale';

    public const RESALE_ACCOUNT_CODE = '4000';

    /** @var array<string, string> */
    public const SYSTEM_CATEGORY_CODES = [
        'Office Supplies' => 'office_supplies',
        'Travel' => 'travel',
        'Software' => 'software',
        'Professional Services' => 'professional_services',
        'Marketing' => 'marketing',
        'Rent' => 'rent',
        'Utilities' => 'utilities',
        'Insurance' => 'insurance',
        'Other' => 'other',
        'Goods Purchased for Resale' => 'goods_purchased_for_resale',
    ];

    public static function systemCodeFor(string $name): ?string
    {
        return self::SYSTEM_CATEGORY_CODES[$name] ?? null;
    }
}
