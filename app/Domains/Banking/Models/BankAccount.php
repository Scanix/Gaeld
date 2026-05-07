<?php

namespace App\Domains\Banking\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use App\Support\Traits\HasPublicUuid;
use Database\Factories\Domains\Banking\Models\BankAccountFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Bank account linked to the organization (e.g. current account, savings).
 *
 * Optionally tied to a ledger {@see Account} for automatic journal entries.
 * Supports soft-deletes and audit logging.
 *
 * @property int $id
 * @property string $uuid
 * @property string $organization_id
 * @property int|null $account_id
 * @property string $name
 * @property string|null $iban
 * @property string|null $qr_iban
 * @property string|null $bank_name
 * @property string|null $bic
 * @property string $currency
 * @property string $balance
 * @property bool $is_active
 * @property bool $is_mixed_use
 * @property bool $is_default_for_invoicing
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Account|null $ledgerAccount
 * @property-read Collection<int, BankTransaction> $transactions
 */
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'account_id',
        'name',
        'iban',
        'qr_iban',
        'bank_name',
        'bic',
        'currency',
        'balance',
        'is_active',
        'is_mixed_use',
        'is_default_for_invoicing',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_mixed_use' => 'boolean',
            'is_default_for_invoicing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Derive the current balance from the linked GL account's journal lines.
     *
     * Asset (debit-normal): balance = SUM(debit) − SUM(credit).
     * Falls back to the denormalized `balance` column when no GL account is linked.
     */
    public function derivedBalance(): string
    {
        if (! $this->account_id) {
            return (string) ($this->balance ?? '0');
        }

        /** @var object{debit_total: string|null, credit_total: string|null}|null $row */
        $row = TransactionLine::query()
            ->where('account_id', $this->account_id)
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit_total, COALESCE(SUM(credit), 0) AS credit_total')
            ->first();

        $debit = (string) ($row->debit_total ?? '0');
        $credit = (string) ($row->credit_total ?? '0');

        return Money::subtract($debit, $credit);
    }
}
