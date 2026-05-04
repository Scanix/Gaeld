<?php

namespace App\Domains\Payroll\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Database\Factories\Domains\Payroll\Models\EmployeeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Employee record within an organization's payroll module.
 *
 * Tracks AHV number, salary, entry/exit dates, and source-tax liability.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $ahv_number
 * @property Carbon $entry_date
 * @property Carbon|null $exit_date
 * @property string $gross_salary
 * @property bool $is_active
 * @property bool $is_source_tax_subject
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    protected $appends = ['status'];

    // ahv_number is hidden from array/JSON serialization; access explicitly only.
    protected $hidden = ['ahv_number'];

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'iban',
        'ahv_number',
        'entry_date',
        'exit_date',
        'gross_salary',
        'is_active',
        'is_source_tax_subject',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'exit_date' => 'date',
            'gross_salary' => 'decimal:2',
            'is_active' => 'boolean',
            'is_source_tax_subject' => 'boolean',
            // ahv_number is encrypted at rest using Laravel's Encrypter (APP_KEY).
            // Stored ciphertext is never interpretable without the application key.
            'ahv_number' => 'encrypted',
            // IBAN is encrypted at rest — same rationale as ahv_number.
            'iban' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SalarySlip, $this> */
    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }
}
