<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $reference
 * @property string|null $description
 * @property bool $is_posted
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class JournalEntry extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'date',
        'reference',
        'description',
        'is_posted',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_posted' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function isBalanced(): bool
    {
        $totals = $this->lines()->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        return bccomp($totals->total_debit ?? '0', $totals->total_credit ?? '0', 2) === 0;
    }

    public function totalDebit(): string
    {
        return (string) $this->lines()->sum('debit');
    }

    public function totalCredit(): string
    {
        return (string) $this->lines()->sum('credit');
    }
}
