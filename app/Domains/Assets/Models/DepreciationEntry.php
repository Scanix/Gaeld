<?php

namespace App\Domains\Assets\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Support\Traits\Auditable;
use App\Support\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monthly depreciation entry generated for a fixed asset.
 *
 * @property int $id
 * @property string $uuid
 * @property string $fixed_asset_id
 * @property string $journal_entry_id
 * @property string $amount
 * @property Carbon $period_date
 * @property Carbon|null $created_at
 */
class DepreciationEntry extends Model
{
    use Auditable, HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
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

    /** @return BelongsTo<FixedAsset, $this> */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
