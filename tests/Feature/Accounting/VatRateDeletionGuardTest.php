<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class VatRateDeletionGuardTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->organization = Organization::create(['name' => 'VAT Rate Test Org', 'currency' => 'CHF']);
        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');
        $this->ensureSubscriptionIfSaas($this->organization);
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function makeVatRate(): VatRate
    {
        return VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => '8.10',
            'code' => 'STD',
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_delete_an_unused_vat_rate(): void
    {
        $vatRate = $this->makeVatRate();

        $this->asOwner()
            ->delete("/accounting/vat-rates/{$vatRate->uuid}")
            ->assertRedirect(route('accounting.vat-rates'));

        $this->assertModelMissing($vatRate);
    }

    public function test_cannot_delete_vat_rate_used_by_an_invoice_line(): void
    {
        $vatRate = $this->makeVatRate();

        $invoice = Invoice::factory()->for($this->organization)->create();
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulting',
            'quantity' => 1,
            'unit_price' => '100.00',
            'amount' => '100.00',
            'vat_rate_id' => $vatRate->id,
            'vat_amount' => '8.10',
            'sort_order' => 0,
        ]);

        $this->asOwner()
            ->delete("/accounting/vat-rates/{$vatRate->uuid}")
            ->assertSessionHasErrors('vat_rate');

        $this->assertModelExists($vatRate);
    }

    public function test_cannot_delete_vat_rate_used_by_an_expense(): void
    {
        $vatRate = $this->makeVatRate();

        Expense::factory()->for($this->organization)->create(['vat_rate_id' => $vatRate->id]);

        $this->asOwner()
            ->delete("/accounting/vat-rates/{$vatRate->uuid}")
            ->assertSessionHasErrors('vat_rate');

        $this->assertModelExists($vatRate);
    }

    public function test_cannot_delete_vat_rate_used_by_a_posted_vat_entry(): void
    {
        $vatRate = $this->makeVatRate();

        $account = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1170',
            'name' => 'VAT Payable',
            'type' => AccountType::Liability->value,
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2025-06-30',
            'reference' => 'JE-VAT-1',
            'description' => 'VAT settlement',
            'is_posted' => true,
            'type' => 'vat_settlement',
        ]);

        VatEntry::create([
            'journal_entry_id' => $journalEntry->id,
            'vat_rate_id' => $vatRate->id,
            'base_amount' => '100.00',
            'vat_amount' => '8.10',
            'type' => VatEntryType::Output->value,
        ]);

        // Without the guard, deleting a VAT rate referenced by a posted
        // vat_entries row would hit the DB's restrictOnDelete FK constraint
        // and surface as a raw 500 QueryException instead of a friendly error.
        $this->asOwner()
            ->delete("/accounting/vat-rates/{$vatRate->uuid}")
            ->assertSessionHasErrors('vat_rate');

        $this->assertModelExists($vatRate);
    }
}
