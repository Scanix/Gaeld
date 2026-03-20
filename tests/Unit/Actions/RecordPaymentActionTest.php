<?php

namespace Tests\Unit\Actions;

use App\Domains\Invoicing\Actions\RecordPaymentAction;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceService;
use Mockery;
use Tests\TestCase;

class RecordPaymentActionTest extends TestCase
{
    private RecordPaymentAction $action;

    private $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = Mockery::mock(InvoiceService::class);
        $this->action = new RecordPaymentAction($this->invoiceService);
    }

    public function test_rejects_payment_on_draft_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Payments can only be recorded for sent or overdue invoices.');

        $this->action->execute($invoice, new RecordPaymentData('100.00', now()->toDateString(), PaymentMethod::Bank, null));
    }

    public function test_rejects_payment_on_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Payments can only be recorded for sent or overdue invoices.');

        $this->action->execute($invoice, new RecordPaymentData('100.00', now()->toDateString(), PaymentMethod::Bank, null));
    }

    public function test_rejects_payment_on_cancelled_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Cancelled);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage('Payments can only be recorded for sent or overdue invoices.');

        $this->action->execute($invoice, new RecordPaymentData('100.00', now()->toDateString(), PaymentMethod::Bank, null));
    }

    public function test_rejects_overpayment(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent, amountDue: '500.00');

        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('exceeds amount due');

        $this->action->execute($invoice, new RecordPaymentData('600.00', now()->toDateString(), PaymentMethod::Bank, null));
    }

    public function test_accepts_payment_on_sent_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent, amountDue: '500.00');

        $paymentData = new RecordPaymentData('100.00', now()->toDateString(), PaymentMethod::Bank, null);
        $payment = Mockery::mock(InvoicePayment::class);
        $this->invoiceService
            ->shouldReceive('recordPayment')
            ->once()
            ->with($invoice, $paymentData)
            ->andReturn($payment);

        $result = $this->action->execute($invoice, $paymentData);

        $this->assertSame($payment, $result);
    }

    public function test_accepts_payment_on_overdue_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Overdue, amountDue: '500.00');

        $paymentData = new RecordPaymentData('200.00', now()->toDateString(), PaymentMethod::Bank, null);
        $payment = Mockery::mock(InvoicePayment::class);
        $this->invoiceService
            ->shouldReceive('recordPayment')
            ->once()
            ->andReturn($payment);

        $result = $this->action->execute($invoice, $paymentData);

        $this->assertSame($payment, $result);
    }

    public function test_accepts_exact_amount_payment(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent, amountDue: '500.00');

        $paymentData = new RecordPaymentData('500.00', now()->toDateString(), PaymentMethod::Bank, null);
        $payment = Mockery::mock(InvoicePayment::class);
        $this->invoiceService
            ->shouldReceive('recordPayment')
            ->once()
            ->andReturn($payment);

        $result = $this->action->execute($invoice, $paymentData);

        $this->assertSame($payment, $result);
    }

    private function makeInvoice(InvoiceStatus $status, string $amountDue = '0.00'): Invoice
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = $status;
        $invoice->shouldReceive('amountDue')->andReturn($amountDue);

        /** @var Invoice $invoice */
        return $invoice;
    }
}
