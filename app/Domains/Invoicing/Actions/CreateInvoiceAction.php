<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new draft invoice with its line items.
 */
class CreateInvoiceAction
{
    /**
     * Max attempts to regenerate the invoice number when a concurrent
     * insert causes a unique constraint collision on (organization_id, number).
     */
    private const MAX_NUMBER_RETRIES = 5;

    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
        private InvoiceNumberGenerator $numberGenerator,
    ) {}

    public function execute(CreateInvoiceData $data): Invoice
    {
        $number = $data->number;
        $attempts = 0;

        while (true) {
            try {
                return $this->createWithNumber($data, $number);
            } catch (UniqueConstraintViolationException $e) {
                if (! $this->isInvoiceNumberCollision($e)) {
                    throw $e;
                }

                $regenerated = $this->regenerateNumber($data->organizationId, $number);

                if ($regenerated === null || ++$attempts >= self::MAX_NUMBER_RETRIES) {
                    throw $e;
                }

                $number = $regenerated;
            }
        }
    }

    private function createWithNumber(CreateInvoiceData $data, string $number): Invoice
    {
        return DB::transaction(function () use ($data, $number) {
            $customerSnapshot = $data->customerId === null
                ? null
                : Contact::withoutGlobalScope('organization')
                    ->where('organization_id', $data->organizationId)
                    ->findOrFail($data->customerId)
                    ->toInvoiceSnapshot();

            $invoice = Invoice::create([
                'organization_id' => $data->organizationId,
                'customer_id' => $data->customerId,
                'customer_snapshot' => $customerSnapshot,
                'number' => $number,
                'status' => InvoiceStatus::Draft,
                'type' => InvoiceType::Invoice,
                'issue_date' => $data->issueDate,
                'due_date' => $data->dueDate,
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0,
                'currency' => $data->currency,
                'notes' => $data->notes,
                'payment_terms' => $data->paymentTerms,
            ]);

            $this->syncInvoiceLines->create($invoice, $data->lines);

            $invoice->recalculate();

            return $invoice->load('lines');
        });
    }

    /**
     * Only collisions on the (organization_id, number) unique index should trigger
     * a regeneration; other unique violations must bubble up unchanged.
     */
    private function isInvoiceNumberCollision(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'invoices_organization_id_number_unique');
    }

    /**
     * Regenerate a sequential number only when the original matches the
     * auto-generated `{PREFIX}-YYYY-NNN` format (i.e. it was suggested by
     * InvoiceNumberGenerator). User-supplied custom numbers are left alone.
     */
    private function regenerateNumber(string $organizationId, string $currentNumber): ?string
    {
        if (! preg_match('/^([A-Za-z]+)-(\d{4})-\d+$/', $currentNumber, $matches)) {
            return null;
        }

        return $this->numberGenerator->next($organizationId, $matches[1], (int) $matches[2]);
    }
}
