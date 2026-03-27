<?php

namespace Tests\Unit\Actions;

use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use Tests\TestCase;

class DeleteInvoiceActionTest extends TestCase
{
    private DeleteInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DeleteInvoiceAction;
    }

    public function test_rejects_sent_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be deleted.');

        $this->action->execute($invoice);
    }

    public function test_rejects_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be deleted.');

        $this->action->execute($invoice);
    }

    public function test_rejects_cancelled_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Cancelled);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be deleted.');

        $this->action->execute($invoice);
    }

    public function test_deletes_lines_and_invoice_for_draft(): void
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = InvoiceStatus::Draft;
        /** @var Invoice $invoice */
        $linesRelation = Mockery::mock(HasMany::class);
        $linesRelation->shouldReceive('delete')->once();

        $invoice->shouldReceive('lines')->once()->andReturn($linesRelation);
        $invoice->shouldReceive('delete')->once();

        $this->action->execute($invoice);
    }

    private function makeInvoice(InvoiceStatus $status): Invoice
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = $status;
        /** @var Invoice $invoice */

        return $invoice;
    }
}
