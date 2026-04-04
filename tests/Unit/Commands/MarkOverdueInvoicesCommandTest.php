<?php

namespace Tests\Unit\Commands;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkOverdueInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_sent_invoices_past_due_date_as_overdue(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:mark-overdue')
            ->assertSuccessful()
            ->expectsOutputToContain('1 invoice(s) marked as overdue');

        $this->assertDatabaseHas('invoices', [
            'status' => InvoiceStatus::Overdue->value,
        ]);
    }

    public function test_does_not_mark_invoices_not_yet_due(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Sent,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:mark-overdue')
            ->assertSuccessful()
            ->expectsOutputToContain('0 invoice(s) marked as overdue');

        $this->assertDatabaseHas('invoices', [
            'status' => InvoiceStatus::Sent->value,
        ]);
    }

    public function test_does_not_mark_draft_invoices(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Draft,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:mark-overdue')
            ->assertSuccessful()
            ->expectsOutputToContain('0 invoice(s) marked as overdue');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Draft->value,
        ]);
    }

    public function test_does_not_mark_already_paid_invoices(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Paid,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:mark-overdue')
            ->assertSuccessful()
            ->expectsOutputToContain('0 invoice(s) marked as overdue');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_handles_no_invoices(): void
    {
        $this->artisan('invoices:mark-overdue')
            ->assertSuccessful()
            ->expectsOutputToContain('0 invoice(s) marked as overdue');

        $this->assertDatabaseCount('invoices', 0);
    }
}
