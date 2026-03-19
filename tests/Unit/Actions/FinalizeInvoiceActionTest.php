<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        $invoice->organization_id = 1;
        $invoice->total = 1000;
        $invoice->number = 'INV-001';
        $invoice->issue_date = now();

        $customer = Mockery::mock();
        $customer->name = 'Test Customer';
        $invoice->shouldReceive('getAttribute')->with('customer')->andReturn($customer);

        $arAccount = Mockery::mock(Account::class)->makePartial();
        $arAccount->id = 10;
        $revenueAccount = Mockery::mock(Account::class)->makePartial();
        $revenueAccount->id = 20;

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 100;

        $this->ledgerService
            ->shouldReceive('resolveAccount')
            ->twice()
            ->andReturn($arAccount, $revenueAccount);

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $invoice->shouldReceive('update')->once();
        $invoice->shouldReceive('fresh')->once()->andReturn($invoice);

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
