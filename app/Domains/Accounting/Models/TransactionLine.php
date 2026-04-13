<?php

namespace App\Domains\Accounting\Models;

use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single debit or credit line within a {@see JournalEntry}.
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property string $debit
 * @property string $credit
 * @property string|null $description
 */
class TransactionLine extends Model
{
    /** @use HasFactory<Factory<static>> */
    use Auditable, HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
