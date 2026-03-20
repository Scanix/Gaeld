<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Customer extends Model
{
    use BelongsToOrganization, HasFactory, Searchable, SoftDeletes;

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
        'currency',
        'payment_terms',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'array', // multi-language JSON
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Domains\Invoicing\Models\Invoice::class);
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
        ];
    }

    /**
     * Scope to current organization for multi-tenant search.
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }
}
