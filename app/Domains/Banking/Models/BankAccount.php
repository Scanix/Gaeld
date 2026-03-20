<?php

namespace App\Domains\Banking\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'account_id',
        'name',
        'iban',
        'bank_name',
        'currency',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Accounting\Models\Account::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
