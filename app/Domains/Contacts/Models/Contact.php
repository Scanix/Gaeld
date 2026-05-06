<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Contacts\Enums\ContactType;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use App\Support\Traits\HasPublicUuid;
use Database\Factories\Domains\Contacts\Models\ContactFactory;
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
 * Unified contact record: may be a customer, a supplier, or both.
 *
 * @property int $id
 * @property string $uuid
 * @property string $organization_id
 * @property ContactType|null $type
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $vat_number
 * @property string|null $iban
 * @property string|null $default_expense_category
 * @property string|null $currency
 * @property string|null $payment_terms
 * @property array<string, mixed>|null $notes
 * @property string|null $internal_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Expense> $expenses
 * @property-read Collection<int, ContactPerson> $contactPersons
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasPublicUuid, Searchable, SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'uuid',
        'organization_id',
        'type',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'vat_number',
        'iban',
        'default_expense_category',
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
            'iban' => 'encrypted',
        ];
    }

    public function searchableAs(): string
    {
        return 'contacts';
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'supplier_id');
    }

    /** @return MorphMany<ContactPerson, $this> */
    public function contactPersons(): MorphMany
    {
        return $this->morphMany(ContactPerson::class, 'contactable');
    }

    // ──────────────────────────────────────────────────────────────
    //  Scout
    // ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
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

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }
}
