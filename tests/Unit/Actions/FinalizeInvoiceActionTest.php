<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use Tests\TestCase;

class FinalizeInvoiceActionTest extends TestCase
{
    private FinalizeInvoiceAction $action;

    private $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->action = new FinalizeInvoiceAction($this->ledgerService);
    }

    public function test_rejects_sent_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be finalized');

        $this->action->execute($invoice);
    }

    public function test_rejects_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be finalized');

        $this->action->execute($invoice);
    }

    public function test_rejects_cancelled_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Cancelled);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Only draft invoices can be finalized');

        $this->action->execute($invoice);
    }

    public function test_rejects_invoice_with_no_lines(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft, lineCount: 0);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Cannot finalize an invoice with no line items');

        $this->action->execute($invoice);
    }

    public function test_finalizes_valid_draft_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft, lineCount: 2);

        $this->ledgerService
            ->shouldReceive('postInvoice')
            ->once()
            ->with($invoice)
            ->andReturn($invoice);

        $result = $this->action->execute($invoice);

        $this->assertSame($invoice, $result);
    }

    private function makeInvoice(InvoiceStatus $status, int $lineCount = 1): Invoice
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = $status;

        $linesRelation = Mockery::mock(HasMany::class);
        $linesRelation->shouldReceive('count')->andReturn($lineCount);
        $invoice->shouldReceive('lines')->andReturn($linesRelation);

        return $invoice;
    }
}
