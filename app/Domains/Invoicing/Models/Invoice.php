<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Enums\InvoiceStatus;
// Note: client_id column retained in DB for legacy data; new records use customer_id only.
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Invoice extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'journal_entry_id',
        'number',
        'status',
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

    public function amountPaid(): string
    {
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
        $this->subtotal = $this->lines()->sum('amount');
        $this->vat_amount = $this->lines()->sum('vat_amount');
        $this->total = bcadd($this->subtotal, $this->vat_amount, 2);
        $this->save();
    }
}
