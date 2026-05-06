<?php

namespace App\Domains\Contacts\Models;

use Database\Factories\Domains\Contacts\Models\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Supplier scoped view of a Contact: contacts with is_supplier = true.
 *
 * All schema, relations, and Scout config live in the Contact base class.
 * This class exists for backward-compatibility with existing code that
 * references Supplier::class (expenses FK, policies, queries, etc.).
 */
class Supplier extends Contact
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('supplier', fn ($q) => $q->where('is_supplier', true));

        static::creating(function (self $model) {
            $model->is_supplier = true;
        });
    }

    public function searchableAs(): string
    {
        return 'suppliers';
    }
}
