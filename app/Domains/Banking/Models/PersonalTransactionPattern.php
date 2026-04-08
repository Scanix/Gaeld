<?php

namespace App\Domains\Banking\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks counterparty names that the user has consistently marked as personal.
 *
 * Used by the SuggestionService to auto-suggest "Privé" on future imports
 * when the same counterparty appears again on a mixed-use bank account.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $counterparty_name
 * @property int $hit_count
 * @property Carbon $last_seen_at
 */
class PersonalTransactionPattern extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'counterparty_name',
        'hit_count',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
