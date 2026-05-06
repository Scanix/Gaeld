<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Organizations\Services\ChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ChecklistFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private ChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->service = app(ChecklistService::class);
    }

    public function test_empty_org_returns_two_tier_structure(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        $this->assertArrayHasKey('getting_started', $checklist);
        $this->assertArrayHasKey('accounting', $checklist);
        $this->assertCount(5, $checklist['getting_started']);
        $this->assertCount(9, $checklist['accounting']);

        foreach ([...$checklist['getting_started'], ...$checklist['accounting']] as $item) {
            $this->assertFalse($item['done'], "Item {$item['key']} should be not done for empty org");
        }
    }

    public function test_checklist_returns_expected_keys(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        $gettingStartedKeys = array_column($checklist['getting_started'], 'key');
        $this->assertContains('checklist_profile_complete', $gettingStartedKeys);
        $this->assertContains('checklist_chart_configured', $gettingStartedKeys);
        $this->assertContains('checklist_customer_created', $gettingStartedKeys);
        $this->assertContains('checklist_bank_account_created', $gettingStartedKeys);
        $this->assertContains('checklist_invoices_created', $gettingStartedKeys);

        $accountingKeys = array_column($checklist['accounting'], 'key');
        $this->assertContains('checklist_expenses_posted', $accountingKeys);
        $this->assertContains('checklist_bank_imported', $accountingKeys);
        $this->assertContains('checklist_reconciliation_done', $accountingKeys);
        $this->assertContains('checklist_vat_declared', $accountingKeys);
        $this->assertContains('checklist_depreciation_posted', $accountingKeys);
        $this->assertContains('checklist_social_charges', $accountingKeys);
        $this->assertContains('checklist_year_end_closed', $accountingKeys);
        $this->assertContains('checklist_fiduciary_exported', $accountingKeys);
        $this->assertContains('checklist_data_imported', $accountingKeys);
    }

    public function test_chart_configured_is_done_when_accounts_exist(): void
    {
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist['getting_started'])->firstWhere('key', 'checklist_chart_configured');

        $this->assertTrue($item['done']);
    }

    public function test_invoices_created_is_done_when_invoice_exists(): void
    {
        Account::create(['organization_id' => $this->org->id, 'code' => '1100', 'name' => 'AR', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3900', 'name' => 'Rounding', 'type' => AccountType::Revenue->value]);

        $customer = Contact::create(['organization_id' => $this->org->id, 'name' => 'Client AG']);

        app(CreateInvoiceAction::class)->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service', 'quantity' => '1', 'unit_price' => '500.00'],
            ],
        ]));

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist['getting_started'])->firstWhere('key', 'checklist_invoices_created');

        $this->assertTrue($item['done']);
    }

    public function test_expenses_posted_is_done_when_posted_expense_exists(): void
    {
        Expense::create([
            'organization_id' => $this->org->id,
            'description' => 'Office supplies',
            'category' => 'Office',
            'amount' => '150.00',
            'date' => '2026-01-10',
            'status' => 'posted',
        ]);

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist['accounting'])->firstWhere('key', 'checklist_expenses_posted');

        $this->assertTrue($item['done']);
    }

    public function test_each_item_has_href(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        foreach ([...$checklist['getting_started'], ...$checklist['accounting']] as $item) {
            $this->assertArrayHasKey('href', $item, "Item {$item['key']} should have an href");
            $this->assertNotEmpty($item['href']);
        }
    }
}
