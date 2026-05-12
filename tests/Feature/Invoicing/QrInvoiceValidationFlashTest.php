<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Actions\SendInvoiceAction;
use App\Domains\Invoicing\Actions\SendInvoiceReminderAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\QrBillValidationException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class QrInvoiceValidationFlashTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_qr_pdf_download_flashes_actionable_message_for_qr_iban_validation_error(): void
    {
        $this->setUpOrganization();

        // The controller short-circuits with a configuration warning when
        // qr_iban is empty (covered by a separate test). Here we simulate the
        // org having one configured so the action runs and we can assert the
        // detailed validation-error flash message.
        BankAccount::create([
            'organization_id' => $this->org->id,
            'name' => 'QR-Bill account',
            'currency' => 'CHF',
            'qr_iban' => 'CH4431999123000889012',
            'is_default_for_invoicing' => true,
            'is_active' => true,
        ]);

        $customer = Contact::factory()->for($this->org, 'organization')->create();
        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer, 'customer')
            ->create(['status' => InvoiceStatus::Sent]);

        $this->mock(GenerateQrInvoicePdfAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new QrBillValidationException(['QR-IBAN is invalid.']));
        });

        $response = $this->actAsOrg()
            ->from(route('invoices.show', $invoice))
            ->get(route('invoices.qr-pdf', $invoice));

        $expected = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_qr_iban'),
        ]).' '.__('app.qr_iban_help_where_to_find')
            .' — '.__('app.qr_invoice_error_details_label').' QR-IBAN is invalid.';

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error', $expected);
    }

    public function test_invoice_send_flashes_actionable_message_for_qr_validation_error(): void
    {
        $this->setUpOrganization();

        $customer = Contact::factory()->for($this->org, 'organization')->create();
        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer, 'customer')
            ->create(['status' => InvoiceStatus::Sent]);

        $this->mock(SendInvoiceAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new QrBillValidationException(['Creditor account is invalid.']));
        });

        $response = $this->actAsOrg()
            ->from(route('invoices.show', $invoice))
            ->post(route('invoices.send', $invoice));

        $expected = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_creditor'),
        ]).' — '.__('app.qr_invoice_error_details_label').' Creditor account is invalid.';

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error', $expected);
    }

    public function test_invoice_reminder_uses_generic_flash_error_when_exception_message_is_empty(): void
    {
        $this->setUpOrganization();

        $customer = Contact::factory()->for($this->org, 'organization')->create();
        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer, 'customer')
            ->create(['status' => InvoiceStatus::Sent]);

        $this->mock(SendInvoiceReminderAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new InvalidInvoiceStateException(''));
        });

        $response = $this->actAsOrg()
            ->from(route('invoices.show', $invoice))
            ->post(route('invoices.reminder', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error', __('app.unexpected_error'));
    }
}
