<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Contacts\Models\Customer;
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
 * Schedule for automatically generating invoices at a recurring frequency.
 *
 * Holds the template data used to create each new invoice and tracks
 * the next issue date to determine when the next invoice is due.
 *
 * @property int $id
 * @property string $uuid
 * @property string $organization_id
 * @property int $customer_id
 * @property RecurrenceFrequency $frequency
 * @property Carbon $next_issue_date
 * @property Carbon|null $end_date
 * @property array<string, mixed> $template_data
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Customer $customer
 */
class RecurringInvoice extends Model
{
    use Auditable, BelongsToOrganization, HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organization_id',
        'customer_id',
        'frequency',
        'next_issue_date',
        'end_date',
        'template_data',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'next_issue_date' => 'date',
            'end_date' => 'date',
            'template_data' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $query->where('next_issue_date', '<=', $date);
    }
}
