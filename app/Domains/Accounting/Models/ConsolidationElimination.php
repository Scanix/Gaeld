<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidationElimination extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'consolidation_group_id',
        'account_debit_id',
        'account_credit_id',
        'amount',
        'fiscal_year',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fiscal_year' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ConsolidationGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_debit_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_credit_id');
    }
}
