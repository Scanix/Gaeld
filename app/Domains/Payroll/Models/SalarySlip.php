<?php

namespace App\Domains\Payroll\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $employee_id
 * @property string $organization_id
 * @property int $period_month
 * @property int $period_year
 * @property string $gross_salary
 * @property string $net_salary
 * @property string|null $journal_entry_id
 * @property array $deductions
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
class SalarySlip extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'period_month',
        'period_year',
        'gross_salary',
        'net_salary',
        'journal_entry_id',
        'deductions',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'deductions' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
