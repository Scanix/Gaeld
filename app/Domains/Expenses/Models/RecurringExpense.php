<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use App\Support\Traits\HasPublicUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schedule for automatically generating expenses at a recurring frequency.
 *
 * Holds all the fields needed to create each new expense and tracks
 * the next due date to determine when the next expense should be created.
 *
 * @property int $id
 * @property string $uuid
 * @property string $organization_id
 * @property int|null $supplier_id
 * @property string $category
 * @property string|null $description
 * @property string $amount
 * @property string $vat_amount
 * @property int|null $vat_rate_id
 * @property string|null $vendor
 * @property string $currency
 * @property string|null $payment_method
 * @property string|null $expense_account_code
 * @property string|null $bank_account_code
 * @property RecurrenceFrequency $frequency
 * @property Carbon $next_due_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Contact|null $supplier
 */
class RecurringExpense extends Model
{
    use Auditable, BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organization_id',
        'supplier_id',
        'category',
        'description',
        'amount',
        'vat_amount',
        'vat_rate_id',
        'vendor',
        'currency',
        'payment_method',
        'expense_account_code',
        'bank_account_code',
        'frequency',
        'next_due_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'next_due_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDue(Builder $query, Carbon $date): Builder
    {
        return $query->where('next_due_date', '<=', $date);
    }
}
