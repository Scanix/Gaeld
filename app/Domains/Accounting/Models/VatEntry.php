<?php

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatEntry extends Model
{
    use HasFactory;

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
        ];
    }

    public const TYPE_INPUT = 'input';   // VAT on purchases (Vorsteuer)
    public const TYPE_OUTPUT = 'output'; // VAT on sales (Umsatzsteuer)

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }
}
