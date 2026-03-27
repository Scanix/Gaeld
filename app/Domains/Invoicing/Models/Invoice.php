<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $customer_id
 * @property string|null $journal_entry_id
 * @property string|null $number
 * @property InvoiceStatus $status
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property string $currency
 * @property string|null $notes
 * @property string|null $payment_terms
 * @property string|null $qr_reference
 * @property string|null $qr_type
 * @property string|null $qr_iban
 * @property string|null $justificatif_path
 * @property InvoiceType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 */
class Invoice extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'journal_entry_id',
        'number',
        'status',
        'type',
        'related_invoice_id',
        'issue_date',
        'due_date',
        'subtotal',
        'vat_amount',
        'total',
        'currency',
        'notes',
        'payment_terms',
        'qr_reference',
        'qr_type',
        'qr_iban',
        'justificatif_path',
        'reminder_count',
        'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'type' => InvoiceType::class,
            'reminder_count' => 'integer',
            'last_reminded_at' => 'datetime',
        ];
    }

    // STATUS_* constants removed — use InvoiceStatus enum: InvoiceStatus::Draft, ::Sent, ::Paid, ::Overdue, ::Cancelled

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'related_invoice_id');
    }

    public function scopeOfType(Builder $query, InvoiceType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Sent)
            ->where('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent
            && $this->due_date->isBefore(now()->startOfDay());
    }

    public function amountPaid(): string
    {
        if (isset($this->attributes['payments_sum_amount'])) {
            return (string) $this->attributes['payments_sum_amount'];
        }

        return (string) $this->payments()->sum('amount');
    }

    public function amountDue(): string
    {
        return bcsub((string) $this->total, $this->amountPaid(), 2);
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->amountDue(), '0', 2) <= 0;
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'number' => $this->number ?? '',
            'status' => $this->status?->value ?? '',
            'customer_name' => $this->customer?->name ?? '',
            'total' => (float) $this->total,
            'currency' => $this->currency,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }

    public function recalculate(): void
    {
        $totals = $this->lines()
            ->selectRaw('SUM(amount) as total_amount, SUM(vat_amount) as total_vat')
            ->first();

        $this->subtotal = $totals->total_amount ?? '0';
        $this->vat_amount = $totals->total_vat ?? '0';
        $this->total = bcadd($this->subtotal, $this->vat_amount, 2);
        $this->save();
    }
}
