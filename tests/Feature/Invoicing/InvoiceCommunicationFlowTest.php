<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Mail\InvoiceMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceMailerService;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class InvoiceCommunicationFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_invoice_email_uses_the_default_account_iban_when_no_qr_iban_is_configured(): void
    {
        $this->setUpOrganization();
        $this->organization->update([
            'legal_name' => 'Test GmbH',
            'address' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);

        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Operating account',
            'iban' => 'CH9300762011623852957',
            'currency' => 'CHF',
            'is_active' => true,
        ]);
        $customer = Contact::factory()->for($this->organization)->create([
            'name' => 'Client AG',
            'email' => 'billing@client.test',
            'address' => 'Lagerstrasse 5',
            'postal_code' => '8004',
            'city' => 'Zürich',
        ]);
        $invoice = Invoice::factory()
            ->for($this->organization)
            ->for($customer, 'customer')
            ->sent()
            ->create([
                'number' => 'INV-2026-001',
                'subtotal' => '100.00',
                'total' => '100.00',
                'issue_date' => '2026-01-15',
                'due_date' => '2026-02-15',
            ]);

        $this->assertNull($bankAccount->qr_iban);
        $violations = app(SwissQrInvoiceService::class)
            ->buildQrBill($invoice, $this->organization)
            ->getViolations();

        $this->assertCount(
            0,
            $violations,
            implode('; ', array_map(fn ($violation): string => $violation->getMessage(), iterator_to_array($violations))),
        );

        $this->mock(GenerateQrInvoicePdfAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andReturn('%PDF-test');
        });
        Mail::fake();

        app(InvoiceMailerService::class)->sendInvoice($invoice->load('customer'));

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice): bool {
            return $mail->invoice->is($invoice);
        });
    }

    public function test_qr_pdf_endpoint_accepts_the_default_account_iban(): void
    {
        $this->setUpOrganization();
        $this->organization->update([
            'legal_name' => 'Test GmbH',
            'address' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
        BankAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Operating account',
            'iban' => 'CH9300762011623852957',
            'currency' => 'CHF',
            'is_active' => true,
        ]);
        $customer = Contact::factory()->for($this->organization)->create([
            'address' => 'Lagerstrasse 5',
            'postal_code' => '8004',
            'city' => 'Zürich',
        ]);
        $invoice = Invoice::factory()
            ->for($this->organization)
            ->for($customer, 'customer')
            ->sent()
            ->create();

        $this->mock(GenerateQrInvoicePdfAction::class, function (MockInterface $mock) use ($invoice): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(fn (Invoice $candidate): bool => $candidate->is($invoice))
                ->andReturn('%PDF-test');
        });

        $response = $this->actAsOrg()->get(route('invoices.qr-pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF-test', $response->getContent());
    }
}
