<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Enums\ExpenseType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Database\Factories\Domains\Expenses\Models\ExpenseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * Business expense (purchase, receipt) recorded by the organization.
 *
 * May be linked to a supplier, a VAT rate, and an accounting journal entry.
 * Supports receipt OCR, full-text search, and soft-deletes.
 *
 * @property string $id
 * @property string $organization_id
 * @property int|null $user_id
 * @property string|null $journal_entry_id
 * @property int|null $vat_rate_id
 * @property string $category
 * @property string|null $description
 * @property string $amount
 * @property string $vat_amount
 * @property Carbon $date
 * @property string|null $vendor
 * @property string|null $receipt_path
 * @property ExpenseStatus $status
 * @property string $currency
 * @property ExpenseType $type
 * @property int|null $supplier_id
 * @property string|null $payment_method
 * @property string|null $expense_account_code
 * @property string|null $bank_account_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read JournalEntry|null $journalEntry
 * @property-read VatRate|null $vatRate
 * @property-read Contact|null $supplier
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
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
        'type',
        'supplier_id',
        'payment_method',
        'expense_account_code',
        'bank_account_code',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'status' => ExpenseStatus::class,
            'type' => ExpenseType::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
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
