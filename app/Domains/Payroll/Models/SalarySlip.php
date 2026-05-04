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
 * Monthly salary slip for an employee, including gross/net amounts and deductions.
 *
 * Optionally linked to a posted journal entry for accounting integration.
 *
 * @property string $id
 * @property string $employee_id
 * @property string $organization_id
 * @property int $period_month
 * @property int $period_year
 * @property string $gross_salary
 * @property string $net_salary
 * @property string|null $journal_entry_id
 * @property array<int, mixed> $deductions
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
class SalarySlip extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $appends = ['status', 'month_label', 'employee_name'];

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

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function getStatusAttribute(): string
    {
        return $this->isPosted() ? 'posted' : 'draft';
    }

    public function getMonthLabelAttribute(): string
    {
        $monthName = Carbon::createFromDate($this->period_year, $this->period_month, 1)
            ->translatedFormat('F Y');

        return $monthName;
    }

    public function getEmployeeNameAttribute(): string
    {
        // Only resolve when relation is already loaded — avoids lazy-loading
        // violations when the slip is serialized in contexts where Employee
        // wasn't eager-loaded (e.g. EmployeeController::show via $employee->salarySlips).
        $employee = $this->relationLoaded('employee') ? $this->getRelation('employee') : null;

        return $employee instanceof Employee ? $employee->fullName() : '';
    }
}
