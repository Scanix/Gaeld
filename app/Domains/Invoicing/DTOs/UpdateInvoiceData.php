<?php

namespace App\Domains\Invoicing\DTOs;

/**
 * DTO for updating an invoice.
 *
 * Shares the same structure and validation as CreateInvoiceData.
 * Extends CreateInvoiceData to eliminate duplication.
 */
readonly class UpdateInvoiceData extends CreateInvoiceData
{
}
