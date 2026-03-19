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
	public static function fromArray(array $data): self
	{
		return new self(
			organizationId: $data['organization_id'],
			customerId: $data['customer_id'],
			number: $data['number'],
			issueDate: $data['issue_date'],
			dueDate: $data['due_date'],
			currency: $data['currency'] ?? 'CHF',
			notes: $data['notes'] ?? null,
			paymentTerms: $data['payment_terms'] ?? null,
			lines: $data['lines'],
		);
	}
}
