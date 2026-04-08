<?php

namespace App\Domains\Banking\Models;

use App\Domains\Banking\Enums\CamtFormat;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Record of a CAMT file (camt.053 / camt.054) imported for a bank account.
 *
 * Serves as the parent grouping for the individual {@see BankTransaction}
 * rows parsed from the XML statements.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $bank_account_id
 * @property string $filename
 * @property CamtFormat $format
 * @property string|null $statement_id
 * @property int $transaction_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read BankAccount $bankAccount
 * @property-read Collection<int, BankTransaction> $transactions
 */
class BankImport extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'filename',
        'format',
        'statement_id',
        'transaction_count',
    ];

    protected function casts(): array
    {
        return [
            'format' => CamtFormat::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_import_id');
    }
}
