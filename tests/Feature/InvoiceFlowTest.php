<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Models\Client;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_invoice_flow(): void
    {
        // Setup
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        $ar = Account::create([
            'organization_id' => $org->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => Account::TYPE_ASSET,
        ]);

        $revenue = Account::create([
            'organization_id' => $org->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => Account::TYPE_REVENUE,
        ]);

        $bank = Account::create([
            'organization_id' => $org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => Account::TYPE_ASSET,
        ]);

        $vatRate = VatRate::create([
            'organization_id' => $org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Test Client AG',
        ]);

        // 1. Create invoice
        $action = new CreateInvoiceAction();
        $invoice = $action->execute([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'number' => 'INV-2026-001',
            'issue_date' => '2026-03-16',
            'due_date' => '2026-04-15',
        ], [
            [
                'description' => 'Web Development',
                'quantity' => 10,
                'unit_price' => 150.00,
                'vat_rate_id' => $vatRate->id,
            ],
        ]);

        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals('1500.00', $invoice->subtotal);

        // 2. Finalize invoice (posts to ledger)
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->finalizeInvoice($invoice);

        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertTrue($invoice->journalEntry->isBalanced());

        // 3. Record payment
        $invoice = $invoiceService->recordPayment($invoice, (float) $invoice->total);

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
    }
}
