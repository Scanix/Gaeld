<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_marked_overdue_invoice_receives_a_payment_reminder(): void
    {
        $this->setUpOrganization();
        Mail::fake();

        $customer = Contact::factory()
            ->for($this->organization)
            ->create(['email' => 'billing@reminder.test']);
        $invoice = Invoice::factory()
            ->for($this->organization)
            ->for($customer, 'customer')
            ->sent()
            ->create([
                'number' => 'INV-REMINDER-001',
                'issue_date' => now()->subDays(30)->toDateString(),
                'due_date' => now()->subDays(10)->toDateString(),
                'total' => '1250.00',
            ]);

        $this->artisan('invoices:mark-overdue')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
        $this->assertTrue($invoice->isOverdue());
        $this->assertCount(1, Invoice::overdue()->get());

        app()->call([app(SendPaymentRemindersJob::class), 'handle']);

        $invoice->refresh();
        $this->assertSame(1, $invoice->reminder_count);
        $this->assertNotNull($invoice->last_reminded_at);
        Mail::assertSent(InvoiceReminderMail::class, function (InvoiceReminderMail $mail) use ($invoice): bool {
            return $mail->invoice->is($invoice);
        });

        $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
    }
}
