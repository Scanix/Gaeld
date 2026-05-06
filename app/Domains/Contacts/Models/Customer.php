<?php

namespace App\Domains\Contacts\Models;

use Database\Factories\Domains\Contacts\Models\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Backward-compatible alias of Contact.
 *
 * Customers and suppliers were merged into a single `contacts` table; this
 * subclass is kept only so existing code that types against Customer::class
 * (FK relations, factories, API resources) continues to compile. It carries
 * no scope and no behavior of its own.
 */
class Customer extends Contact
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;
}
