<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => AccountType::class,
        ];
    }

    /**
     * Account types following Swiss SME chart of accounts (Kontenrahmen KMU).
     */
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function balance(): string
    {
        $debits = (string) $this->transactionLines()->sum('debit');
        $credits = (string) $this->transactionLines()->sum('credit');

        return match ($this->type) {
            AccountType::Asset, AccountType::Expense => bcsub($debits, $credits, 2),
            default => bcsub($credits, $debits, 2),
        };
    }
}
