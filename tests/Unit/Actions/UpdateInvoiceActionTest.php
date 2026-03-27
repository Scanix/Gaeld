<?php

namespace Tests\Unit\Actions;

use App\Domains\Invoicing\Actions\SyncInvoiceLinesAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Mockery;
use Tests\TestCase;

class UpdateInvoiceActionTest extends TestCase
{
    private $syncInvoiceLines;

    private UpdateInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncInvoiceLines = Mockery::mock(SyncInvoiceLinesAction::class);
        $this->action = new UpdateInvoiceAction($this->syncInvoiceLines);
    }

    public function test_rejects_sent_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be updated.');

        $this->action->execute($invoice, $this->makeData());
    }

    public function test_rejects_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be updated.');

        $this->action->execute($invoice, $this->makeData());
    }

    public function test_rejects_cancelled_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Cancelled);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be updated.');

        $this->action->execute($invoice, $this->makeData());
    }

    public function test_updates_invoice_and_replaces_lines_for_draft(): void
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = InvoiceStatus::Draft;
        /** @var Invoice $invoice */
        $data = $this->makeData();

        $invoice->shouldReceive('update')->once()->with([
            'customer_id' => 'customer-1',
            'number' => 'INV-200',
            'issue_date' => '2025-02-01',
            'due_date' => '2025-03-03',
            'currency' => 'EUR',
            'notes' => 'Updated notes',
            'payment_terms' => '30 days',
        ]);
        $invoice->shouldReceive('recalculate')->once();
        $invoice->shouldReceive('load')->once()->with('lines')->andReturnSelf();

        $this->syncInvoiceLines
            ->shouldReceive('replace')
            ->once()
            ->with($invoice, $data->lines);

        $result = $this->action->execute($invoice, $data);

        $this->assertSame($invoice, $result);
    }

    private function makeInvoice(InvoiceStatus $status): Invoice
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = $status;
        /** @var Invoice $invoice */

        return $invoice;
    }

    private function makeData(): UpdateInvoiceData
    {
        return UpdateInvoiceData::fromArray([
            'organization_id' => 'org-1',
            'customer_id' => 'customer-1',
            'number' => 'INV-200',
            'issue_date' => '2025-02-01',
            'due_date' => '2025-03-03',
            'currency' => 'EUR',
            'notes' => 'Updated notes',
            'payment_terms' => '30 days',
            'lines' => [
                [
                    'description' => 'Line 1',
                    'quantity' => '2.00',
                    'unit_price' => '49.95',
                    'vat_rate_id' => 'vat-1',
                    'sort_order' => 3,
                ],
            ],
        ]);
    }
}
