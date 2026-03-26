<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Supplier extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'vat_number',
        'default_expense_category',
        'currency',
        'iban',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'array', // multi-language JSON
            'iban' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(\App\Domains\Expenses\Models\Expense::class);
    }

    public function contactPersons(): MorphMany
    {
        return $this->morphMany(ContactPerson::class, 'contactable');
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'email' => $this->email ?? '',
            'city' => $this->city ?? '',
            'vat_number' => $this->vat_number ?? '',
            'default_expense_category' => $this->default_expense_category ?? '',
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }
}
