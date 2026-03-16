<?php

namespace App\Domains\Banking\Models;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'journal_entry_id',
        'date',
        'description',
        'amount',
        'type',
        'reference',
        'is_reconciled',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_reconciled' => 'boolean',
        ];
    }

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
