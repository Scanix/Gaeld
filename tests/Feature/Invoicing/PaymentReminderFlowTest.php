<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceMailerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PaymentReminderFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Contact $customer;

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

        $this->customer = Contact::create([
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
    //  InvoiceMailerService::sendReminder()
    // ──────────────────────────────────────────────────────────────

    public function test_reminder_action_sends_mail_and_increments_count(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();

        $this->assertEquals(0, $invoice->reminder_count);

        app(InvoiceMailerService::class)->sendReminder($invoice);

        $invoice->refresh();
        $this->assertEquals(1, $invoice->reminder_count);
        $this->assertNotNull($invoice->last_reminded_at);

        Mail::assertSent(InvoiceReminderMail::class);
    }

    public function test_reminder_uses_outstanding_balance_and_organization_reply_address(): void
    {
        Mail::fake();
        config()->set('mail.from.address', 'mailer@gaeld.test');
        $this->org->update([
            'name' => 'Alpine Services SA',
            'contact_email' => 'billing@alpine.test',
            'locale' => 'fr',
        ]);

        $invoice = $this->createOverdueInvoice();
        InvoicePayment::create([
            'organization_id' => $this->org->id,
            'invoice_id' => $invoice->id,
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => PaymentMethod::Bank->value,
        ]);

        app(InvoiceMailerService::class)->sendReminder($invoice);

        Mail::assertSent(InvoiceReminderMail::class, function (InvoiceReminderMail $mail): bool {
            $envelope = $mail->envelope();

            return $mail->amountDue === '1000.00'
                && $mail->organization->name === 'Alpine Services SA'
                && str_contains($envelope->subject, 'Alpine Services SA')
                && $envelope->from?->address === 'mailer@gaeld.test'
                && $envelope->from?->name === 'Alpine Services SA'
                && $envelope->replyTo[0]->address === 'billing@alpine.test'
                && str_contains($mail->render(), "1'000.00");
        });
    }

    public function test_failed_delivery_does_not_increment_reminder_count(): void
    {
        $invoice = $this->createOverdueInvoice();

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('SMTP unavailable'));

        try {
            app(InvoiceMailerService::class)->sendReminder($invoice);
            $this->fail('Expected the mail delivery exception to bubble up.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $invoice->refresh();
        $this->assertSame(0, $invoice->reminder_count);
        $this->assertNull($invoice->last_reminded_at);
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
        app(InvoiceMailerService::class)->sendReminder($invoice);
    }

    public function test_reminder_action_throws_if_customer_has_no_email(): void
    {
        Mail::fake();

        $noEmailCustomer = Contact::create([
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
        app(InvoiceMailerService::class)->sendReminder($invoice);
    }

    public function test_manual_reminder_respects_cooldown_period(): void
    {
        Mail::fake();

        $invoice = $this->createOverdueInvoice();

        // Simulate a recent reminder (2 days ago — still within 7-day cooldown)
        $invoice->update([
            'last_reminded_at' => Carbon::now()->subDays(2),
            'reminder_count' => 1,
        ]);

        $this->expectException(InvalidInvoiceStateException::class);
        app(InvoiceMailerService::class)->sendReminder($invoice);
        Mail::assertNothingSent();

        $invoice->refresh();
        $this->assertSame(1, $invoice->reminder_count, 'Reminder count should not change during cooldown');
    }
}
