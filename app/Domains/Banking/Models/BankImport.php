<?php

namespace App\Domains\Banking\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankImport extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'filename',
        'format',
        'statement_id',
        'transaction_count',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_import_id');
    }
}
