<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Actions\CreateCreditNoteAction;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class CreditNoteFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Credit Note Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        Account::create(['organization_id' => $this->org->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3900', 'name' => 'Rounding', 'type' => AccountType::Revenue->value]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Client AG',
        ]);
    }

    private function createAndFinalizeInvoice(): Invoice
    {
        $createAction = app(CreateInvoiceAction::class);

        $invoice = $createAction->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-001',
            'issue_date' => '2026-03-15',
            'due_date' => '2026-04-15',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service A', 'quantity' => '2', 'unit_price' => '500.00'],
                ['description' => 'Service B', 'quantity' => '1', 'unit_price' => '250.00'],
            ],
        ]));

        $finalizeAction = app(FinalizeInvoiceAction::class);

        return $finalizeAction->execute($invoice);
    }

    public function test_credit_note_created_from_finalized_invoice(): void
    {
        $invoice = $this->createAndFinalizeInvoice();

        $action = app(CreateCreditNoteAction::class);
        $creditNote = $action->execute($invoice);

        $this->assertEquals(InvoiceType::CreditNote, $creditNote->type);
        $this->assertEquals($invoice->id, $creditNote->related_invoice_id);
        $this->assertStringStartsWith('CN-', $creditNote->number);
        $this->assertEquals(InvoiceStatus::Draft, $creditNote->status);

        // Amounts should be negative
        $this->assertTrue(bccomp((string) $creditNote->total, '0', 2) < 0);
        $this->assertEquals('-1250.00', (string) $creditNote->total);

        // Lines should have "Avoir:" prefix
        $creditNote->load('lines');
        foreach ($creditNote->lines as $line) {
            $this->assertStringStartsWith('Avoir:', $line->description);
        }
    }

    public function test_cannot_create_credit_note_from_draft(): void
    {
        $createAction = app(CreateInvoiceAction::class);

        $invoice = $createAction->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-2026-002',
            'issue_date' => '2026-03-15',
            'due_date' => '2026-04-15',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00'],
            ],
        ]));

        $action = app(CreateCreditNoteAction::class);

        $this->expectException(InvalidInvoiceStateException::class);
        $action->execute($invoice);
    }

    public function test_finalized_credit_note_creates_reversed_journal_entries(): void
    {
        $invoice = $this->createAndFinalizeInvoice();

        $action = app(CreateCreditNoteAction::class);
        $creditNote = $action->execute($invoice);

        // Finalize the credit note
        $finalizeAction = app(FinalizeInvoiceAction::class);
        $creditNote = $finalizeAction->execute($creditNote);

        $creditNote->load('journalEntry.lines.account');
        $this->assertNotNull($creditNote->journal_entry_id);

        $journalEntry = $creditNote->journalEntry;
        $this->assertNotNull($journalEntry);

        // Verify the journal entry is balanced
        $totalDebit = '0';
        $totalCredit = '0';
        foreach ($journalEntry->lines as $line) {
            $totalDebit = bcadd($totalDebit, (string) $line->debit, 2);
            $totalCredit = bcadd($totalCredit, (string) $line->credit, 2);
        }
        $this->assertEquals($totalDebit, $totalCredit, 'Journal entry must be balanced');

        // Credit note should have reversed entries:
        // - Credit AR (not Debit)
        // - Debit Revenue (not Credit)
        $arLine = $journalEntry->lines->first(fn ($l) => $l->account->code === '1100');
        $this->assertNotNull($arLine);
        $this->assertTrue(bccomp((string) $arLine->debit, '0', 2) === 0, 'AR debit should be zero for credit note');
        $this->assertTrue(bccomp((string) $arLine->credit, '0', 2) > 0, 'AR should be credited for credit note');

        $revenueLine = $journalEntry->lines->first(fn ($l) => $l->account->code === '3000');
        $this->assertNotNull($revenueLine);
        $this->assertTrue(bccomp((string) $revenueLine->debit, '0', 2) > 0, 'Revenue should be debited for credit note');
        $this->assertTrue(bccomp((string) $revenueLine->credit, '0', 2) === 0, 'Revenue credit should be zero for credit note');
    }

    public function test_credit_note_linked_to_original(): void
    {
        $invoice = $this->createAndFinalizeInvoice();

        $action = app(CreateCreditNoteAction::class);
        $creditNote = $action->execute($invoice);

        $this->assertEquals($invoice->id, $creditNote->related_invoice_id);

        // Test relationships
        $creditNote->load('relatedInvoice');
        $this->assertEquals($invoice->id, $creditNote->relatedInvoice->id);

        $invoice->load('creditNotes');
        $this->assertCount(1, $invoice->creditNotes);
        $this->assertEquals($creditNote->id, $invoice->creditNotes->first()->id);
    }

    public function test_invoice_query_filters_by_type(): void
    {
        $invoice = $this->createAndFinalizeInvoice();

        $action = app(CreateCreditNoteAction::class);
        $action->execute($invoice);

        $invoices = Invoice::ofType(InvoiceType::Invoice)->get();
        $this->assertCount(1, $invoices);

        $creditNotes = Invoice::ofType(InvoiceType::CreditNote)->get();
        $this->assertCount(1, $creditNotes);
    }

    public function test_credit_note_route(): void
    {
        $invoice = $this->createAndFinalizeInvoice();

        $response = $this->actingAs($this->user)
            ->post(route('invoices.creditNote', $invoice));

        $response->assertRedirect();

        $creditNote = Invoice::where('type', InvoiceType::CreditNote->value)->first();
        $this->assertNotNull($creditNote);
        $this->assertStringStartsWith('CN-', $creditNote->number);
    }
}
