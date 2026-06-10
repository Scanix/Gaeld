<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Actions\CancelInvoiceAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class CancelInvoiceActionTest extends TestCase
{
    private CancelInvoiceAction $action;

    private $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->action = new CancelInvoiceAction($this->ledgerService);
    }

    public function test_rejects_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Cannot cancel an invoice with status: paid.');

        $this->action->execute($invoice);
    }

    public function test_rejects_already_cancelled_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Cancelled);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Cannot cancel an invoice with status: cancelled.');

        $this->action->execute($invoice);
    }

    public function test_cancels_sent_invoice_and_reverses_journal(): void
    {
        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->shouldReceive('getAttribute')->with('lines')->andReturn(new Collection);

        $reversal = Mockery::mock(JournalEntry::class)->makePartial();

        $invoice = $this->makeInvoice(InvoiceStatus::Sent, journalEntryId: 'je-123', journalEntry: $journalEntry);

        $reversalEntry = Mockery::mock(JournalEntry::class)->makePartial();

        $this->ledgerService
            ->shouldReceive('reverseEntry')
            ->once()
            ->with($journalEntry, 'Cancellation of INV-TEST')
            ->andReturn($reversal);

        $this->ledgerService
            ->shouldReceive('postDraft')
            ->once()
            ->with($reversal)
            ->andReturn($reversal);

        $invoice->shouldReceive('update')
            ->once()
            ->with(['status' => InvoiceStatus::Cancelled])
            ->andReturnTrue();

        $invoice->shouldReceive('fresh')
            ->once()
            ->andReturn($invoice);

        $result = $this->action->execute($invoice);

        $this->assertSame($invoice, $result);
    }

    public function test_cancels_draft_invoice_without_reversal(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);

        $this->ledgerService->shouldNotReceive('reverseEntry');

        $invoice->shouldReceive('update')
            ->once()
            ->with(['status' => InvoiceStatus::Cancelled])
            ->andReturnTrue();

        $invoice->shouldReceive('fresh')
            ->once()
            ->andReturn($invoice);

        $result = $this->action->execute($invoice);

        $this->assertSame($invoice, $result);
    }

    private function makeInvoice(InvoiceStatus $status, ?string $journalEntryId = null, $journalEntry = null): Invoice
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = $status;
        $invoice->number = 'INV-TEST';
        $invoice->journal_entry_id = $journalEntryId;

        if ($journalEntry) {
            $invoice->shouldReceive('loadMissing')->with('journalEntry.lines')->andReturnSelf();
            $invoice->shouldReceive('getAttribute')->with('journalEntry')->andReturn($journalEntry);
        }

        return $invoice;
    }
}
