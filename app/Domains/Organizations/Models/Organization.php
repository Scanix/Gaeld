<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Multi-tenant organization (company / business entity).
 *
 * Every domain model is scoped to an organization via `organization_id`.
 * Stores company details (legal name, address, VAT), default currency,
 * locale, fiscal year start, and QR-IBAN for Swiss QR invoices.
 *
 * @property string $id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $canton
 * @property string|null $country
 * @property string|null $vat_number
 * @property string|null $qr_iban
 * @property string $currency
 * @property int|null $fiscal_year_start
 * @property array<int, int>|null $closed_fiscal_years
 * @property string|null $locale
 * @property BusinessType|null $business_type
 * @property bool $require_two_factor
 * @property int|null $default_payment_terms_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $activeSubscription Injected at runtime by a plugin via resolveRelationUsing().
 */
class Organization extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'legal_name',
        'address',
        'city',
        'postal_code',
        'canton',
        'country',
        'vat_number',
        'qr_iban',
        'currency',
        'fiscal_year_start',
        'closed_fiscal_years',
        'locale',
        'business_type',
        'require_two_factor',
        'default_payment_terms_days',
        'default_invoice_notes',
        'logo_path',
        'invoice_header_text',
        'invoice_footer_text',
        'invoice_email_subject',
        'invoice_email_body',
    ];

    protected function casts(): array
    {
        return [
            'require_two_factor' => 'boolean',
            'default_payment_terms_days' => 'integer',
            'closed_fiscal_years' => 'array',
            'business_type' => BusinessType::class,
        ];
    }

    /**
     * Check whether a given fiscal year is closed.
     */
    public function isFiscalYearClosed(int $year): bool
    {
        return in_array($year, $this->closed_fiscal_years ?? [], true);
    }

    /**
     * Mark a fiscal year as closed.
     */
    public function closeFiscalYear(int $year): void
    {
        $closed = $this->closed_fiscal_years ?? [];
        if (! in_array($year, $closed, true)) {
            $closed[] = $year;
            sort($closed);
            $this->update(['closed_fiscal_years' => $closed]);
        }
    }

    /**
     * Reopen a previously closed fiscal year.
     */
    public function reopenFiscalYear(int $year): void
    {
        /** @var int[] $closed */
        $closed = $this->closed_fiscal_years ?? [];
        $closed = array_values(array_filter($closed, fn (int $y) => $y !== $year));
        $this->update(['closed_fiscal_years' => $closed]);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<BankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }
}
