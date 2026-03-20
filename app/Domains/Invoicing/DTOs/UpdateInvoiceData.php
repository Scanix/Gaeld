<?php

namespace App\Domains\Invoicing\DTOs;

/**
 * DTO for updating an invoice.
 *
 * Structurally identical to CreateInvoiceData — both carry the same
 * invoice fields. Separate class provides type safety at the action boundary.
 */
readonly class UpdateInvoiceData extends CreateInvoiceData
{
}
