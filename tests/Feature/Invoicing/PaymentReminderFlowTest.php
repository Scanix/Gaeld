<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\SendInvoiceReminderAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PaymentReminderFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-15 08:00:00');

        $this->setUpOrganization();

        Account::create(['organization_id' => $this->org->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3900', 'name' => 'Rounding', 'type' => AccountType::Revenue->value]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Late Payer AG',
            'email' => 'finance@latepayer.ch',
        ]);
    }

    private function createOverdueInvoice(string $dueDate = '2026-03-01'): Invoice
    {
        $invoice = app(CreateInvoiceAction::class)->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-'.uniqid(),
            'issue_date' => '2026-02-15',
            'due_date' => $dueDate,
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Consulting', 'quantity' => '10', 'unit_price' => '150.00'],
            ],
        ]));

        return app(FinalizeInvoiceAction::class)->execute($invoice);
    }

    // ──────────────────────────────────────────────────────────────
    //  SendInvoiceReminderAction
    // ──────────────────────────────────────────────────────────────

    public function test_reminder_action_sends_mail_and_increments_count(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();

        $this->assertEquals(0, $invoice->reminder_count);

        app(SendInvoiceReminderAction::class)->execute($invoice);

        $invoice->refresh();
        $this->assertEquals(1, $invoice->reminder_count);
        $this->assertNotNull($invoice->last_reminded_at);

        Mail::assertSent(InvoiceReminderMail::class);
    }

    public function test_reminder_action_throws_if_not_overdue(): void
    {
        Mail::fake();

        $invoice = app(CreateInvoiceAction::class)->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-DRAFT-001',
            'issue_date' => '2026-04-15',
            'due_date' => '2026-05-15',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00'],
            ],
        ]));

        $this->expectException(InvalidInvoiceStateException::class);
        app(SendInvoiceReminderAction::class)->execute($invoice);
    }

    public function test_reminder_action_throws_if_customer_has_no_email(): void
    {
        Mail::fake();

        $noEmailCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'No Email AG',
        ]);

        $invoice = app(CreateInvoiceAction::class)->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $noEmailCustomer->id,
            'number' => 'INV-NOEMAIL-001',
            'issue_date' => '2026-02-15',
            'due_date' => '2026-03-01',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00'],
            ],
        ]));

        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);

        $this->expectException(InvalidInvoiceStateException::class);
        app(SendInvoiceReminderAction::class)->execute($invoice);
    }

    // ──────────────────────────────────────────────────────────────
    //  Job: SendPaymentRemindersJob
    // ──────────────────────────────────────────────────────────────

    public function test_job_sends_reminders_for_overdue_invoices(): void
    {
        Mail::fake();

        $this->createOverdueInvoice();

        app(SendPaymentRemindersJob::class)->handle(
            app(SendInvoiceReminderAction::class),
        );

        Mail::assertSent(InvoiceReminderMail::class, 1);
    }

    public function test_job_respects_cooldown_period(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();

        // Simulate a recent reminder (2 days ago — still within 7-day cooldown)
        $invoice->update([
            'last_reminded_at' => Carbon::now()->subDays(2),
            'reminder_count' => 1,
        ]);

        app(SendPaymentRemindersJob::class)->handle(
            app(SendInvoiceReminderAction::class),
        );

        Mail::assertNothingSent();
    }

    public function test_job_sends_after_cooldown_expired(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();

        // Simulate reminder sent 10 days ago (cooldown expired)
        $invoice->update([
            'last_reminded_at' => Carbon::now()->subDays(10),
            'reminder_count' => 1,
        ]);

        app(SendPaymentRemindersJob::class)->handle(
            app(SendInvoiceReminderAction::class),
        );

        Mail::assertSent(InvoiceReminderMail::class, 1);
    }

    public function test_job_skips_paid_invoices(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();
        $invoice->update(['status' => InvoiceStatus::Paid]);

        app(SendPaymentRemindersJob::class)->handle(
            app(SendInvoiceReminderAction::class),
        );

        Mail::assertNothingSent();
    }
}
