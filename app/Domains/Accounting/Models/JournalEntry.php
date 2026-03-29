<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Double-entry accounting journal entry (header).
 *
 * Groups one or more balanced {@see TransactionLine} rows. May be
 * in draft (`is_posted = false`) or posted to the ledger.
 *
 * @property string $id
 * @property Carbon $date
 * @property string $reference
 * @property string|null $description
 * @property bool $is_posted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TransactionLine> $lines
 */
class JournalEntry extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'date',
        'reference',
        'description',
        'is_posted',
        'type',
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
        if ($this->relationLoaded('lines')) {
            return (string) $this->lines->sum('debit');
        }

        return (string) $this->lines()->sum('debit');
    }

    public function totalCredit(): string
    {
        if ($this->relationLoaded('lines')) {
            return (string) $this->lines->sum('credit');
        }

        return (string) $this->lines()->sum('credit');
    }
}
