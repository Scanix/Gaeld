<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment recorded against an invoice (partial or full).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $amount
 * @property Carbon $payment_date
 * @property PaymentMethod $payment_method
 * @property string|null $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read JournalEntry|null $journalEntry
 */
class InvoicePayment extends Model
{
    /** @use HasFactory<Factory<static>> */
    use Auditable, BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'journal_entry_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
