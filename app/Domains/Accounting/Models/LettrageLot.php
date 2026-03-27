<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organization_id
 * @property int $account_id
 * @property string $letter_key
 * @property array $line_ids
 * @property int|null $lettered_by_user_id
 * @property Carbon $lettered_at
 * @property bool $is_reversed
 */
class LettrageLot extends Model
{
    use BelongsToOrganization;

    protected $table = 'lettrage_lots';

    protected $fillable = [
        'organization_id',
        'account_id',
        'letter_key',
        'line_ids',
        'lettered_by_user_id',
        'lettered_at',
        'is_reversed',
    ];

    protected function casts(): array
    {
        return [
            'line_ids' => 'array',
            'lettered_at' => 'datetime',
            'is_reversed' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
