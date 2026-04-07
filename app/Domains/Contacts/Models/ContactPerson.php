<?php

namespace App\Domains\Contacts\Models;

use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Individual contact person attached to a Customer or Supplier (polymorphic).
 *
 * One contact person may be flagged as `is_primary` for its parent entity.
 *
 * @property string $id
 * @property string $contactable_type
 * @property string $contactable_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $position
 * @property bool $is_primary
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 */
class ContactPerson extends Model
{
    use Auditable, HasUuids;

    protected $table = 'contact_persons';

    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
