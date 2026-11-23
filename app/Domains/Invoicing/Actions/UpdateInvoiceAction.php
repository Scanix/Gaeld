<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;

/**
 * Updates a draft invoice and re-syncs its line items.
 */
class UpdateInvoiceAction
{
    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
    ) {}

    public function execute(Invoice $invoice, UpdateInvoiceData $data, bool $syncLines = true): Invoice
    {
        if ($data->commercialReferences === []) {
            return $this->updateDraft($invoice, $data, $syncLines);
        }

        return $invoice->getConnection()->transaction(function () use ($invoice, $data, $syncLines) {
            $locked = $this->lockUnarchived($invoice);

            return $this->updateDraft($locked, $data, $syncLines);
        });
    }

    public function replaceSupportingDocument(Invoice $invoice, UpdateInvoiceData $data, string $path): ?string
    {
        return $invoice->getConnection()->transaction(function () use ($invoice, $data, $path) {
            $locked = $this->lockUnarchived($invoice);
            $previous = $locked->justificatif_path;
            $this->updateDraft($locked, $data, true, $path);

            return $previous;
        });
    }

    private function lockUnarchived(Invoice $invoice): Invoice
    {
        $locked = Invoice::query()->where('organization_id', $invoice->organization_id)->lockForUpdate()->findOrFail($invoice->id);

        if ($locked->archived_at !== null) {
            throw new InvalidInvoiceStateException('Archived invoices cannot be updated.');
        }

        return $locked;
    }

    private function updateDraft(Invoice $invoice, UpdateInvoiceData $data, bool $syncLines, ?string $justificatifPath = null): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new InvalidInvoiceStateException('Only draft invoices can be updated.');
        }

        $invoiceData = [
            ...($data->externalSource !== null ? ['external_source' => $data->externalSource, 'external_id' => $data->externalId] : []),
            'customer_id' => $data->customerId,
            'number' => $data->number,
            'issue_date' => $data->issueDate,
            'due_date' => $data->dueDate,
            'currency' => $data->currency,
            'notes' => $data->notes,
            'payment_terms' => $data->paymentTerms,
            ...$data->commercialReferences,
        ];

        if ($justificatifPath !== null) {
            $invoiceData['justificatif_path'] = $justificatifPath;
        }

        $organizationId = $invoice->getAttribute('organization_id');
        $storedTaxTreatment = $invoice->getRawOriginal('tax_treatment');
        if ($data->taxTreatment !== InvoiceTaxTreatment::Standard
            || ($storedTaxTreatment !== null && $storedTaxTreatment !== InvoiceTaxTreatment::Standard->value)) {
            $invoiceData['tax_treatment'] = $data->taxTreatment;
        }

        if (is_string($organizationId) && $organizationId !== '') {
            $customer = $data->customerId === null
                ? null
                : Contact::withoutGlobalScope('organization')
                    ->where('organization_id', $organizationId)
                    ->findOrFail($data->customerId);
            $data->taxTreatment->validateCustomer($customer);
            $invoiceData['customer_snapshot'] = $customer?->toInvoiceSnapshot();
        }

        $invoice->update($invoiceData);

        if (! $syncLines) {
            return $invoice->load('lines');
        }

        if ($data->taxTreatment->appliesSwissVat()) {
            $this->syncInvoiceLines->replace($invoice, $data->lines);
        } else {
            $this->syncInvoiceLines->replace($invoice, $data->lines, false);
        }

        $invoice->recalculate();

        return $invoice->load('lines');
    }
}
