<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

class RefreshInvoiceCustomerSnapshotAction
{
    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $storedInvoice = Invoice::withoutGlobalScope('organization')
                ->where('organization_id', $invoice->organization_id)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($storedInvoice->archived_at !== null) {
                throw new InvalidInvoiceStateException(__('app.invoice_customer_snapshot_archived'));
            }

            $customer = Contact::withoutGlobalScope('organization')
                ->where('organization_id', $storedInvoice->organization_id)
                ->findOrFail($storedInvoice->customer_id);

            ($storedInvoice->tax_treatment ?? InvoiceTaxTreatment::Standard)->validateCustomer($customer);

            $storedInvoice->update(['customer_snapshot' => $customer->toInvoiceSnapshot()]);

            return $storedInvoice;
        });
    }
}
