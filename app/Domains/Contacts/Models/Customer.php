<?php

namespace App\Domains\Contacts\Models;

use Database\Factories\Domains\Contacts\Models\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Customer scoped view of a Contact: contacts with is_customer = true.
 *
 * All schema, relations, and Scout config live in the Contact base class.
 * This class exists for backward-compatibility with existing code that
 * references Customer::class (invoices FK, policies, queries, etc.).
 */
class Customer extends Contact
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('customer', fn ($q) => $q->where('is_customer', true));

        static::creating(function (self $model) {
            $model->is_customer = true;
        });
    }

    public function searchableAs(): string
    {
        return 'customers';
    }
}
