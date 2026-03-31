<?php

namespace App\Domains\Contacts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Individual contact person attached to a Customer or Supplier (polymorphic).
 *
 * One contact person may be flagged as `is_primary` for its parent entity.
 */
class ContactPerson extends Model
{
    use HasUuids;

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

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
