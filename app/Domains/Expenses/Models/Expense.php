<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Expense extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'journal_entry_id',
        'vat_rate_id',
        'category',
        'description',
        'amount',
        'vat_amount',
        'date',
        'vendor',
        'receipt_path',
        'status',
        'currency',
        'supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'status' => ExpenseStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'description' => $this->description ?? '',
            'vendor' => $this->vendor ?? '',
            'category' => $this->category,
            'amount' => (string) $this->amount,
            'status' => $this->status?->value ?? '',
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }
}
