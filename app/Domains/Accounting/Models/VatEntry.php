<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\VatEntryType;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual VAT line attached to a journal entry.
 *
 * Captures the taxable base amount and the computed VAT amount
 * for a specific rate, distinguishing input vs. output tax via `type`.
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $vat_rate_id
 * @property string $base_amount
 * @property string $vat_amount
 * @property VatEntryType $type
 */
class VatEntry extends Model
{
    /** @use HasFactory<Factory<static>> */
    use Auditable, HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'vat_rate_id',
        'base_amount',
        'vat_amount',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'type' => VatEntryType::class,
        ];
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
}
