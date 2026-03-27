<?php

namespace App\Domains\Assets\Models;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $fixed_asset_id
 * @property string $journal_entry_id
 * @property string $amount
 * @property Carbon $period_date
 * @property Carbon|null $created_at
 */
class DepreciationEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'fixed_asset_id',
        'journal_entry_id',
        'amount',
        'period_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
