<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Invoicing\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string|null $journal_entry_id
 * @property string $amount
 * @property Carbon $payment_date
 * @property PaymentMethod $payment_method
 * @property string|null $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoicePayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
