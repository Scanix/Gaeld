<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Contacts\Enums\ContactType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use App\Support\Traits\HasPublicUuid;
use Database\Factories\Domains\Contacts\Models\CustomerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * Customer to whom the organization issues invoices.
 *
 * Supports multi-language notes, soft-deletes, and full-text search via Scout.
 *
 * @property int $id
 * @property string $uuid
 * @property string $organization_id
 * @property string $name
 * @property ContactType|null $type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $vat_number
 * @property string|null $currency
 * @property string|null $payment_terms
 * @property array<string, mixed>|null $notes
 * @property string|null $internal_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, ContactPerson> $contactPersons
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasPublicUuid, Searchable, SoftDeletes;

    protected $fillable = [
        'uuid',
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
            'notes' => 'array',
            'type' => ContactType::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return MorphMany<ContactPerson, $this> */
    public function contactPersons(): MorphMany
    {
        return $this->morphMany(ContactPerson::class, 'contactable');
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'email' => $this->email ?? '',
            'city' => $this->city ?? '',
            'vat_number' => $this->vat_number ?? '',
            'contact_persons' => $this->contactPersons
                ->map(fn (ContactPerson $cp) => $cp->full_name.' '.$cp->email)
                ->implode(' | '),
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
