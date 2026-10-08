<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Actions\RefreshInvoiceCustomerSnapshotAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class RefreshInvoiceCustomerSnapshotTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->customer = Contact::factory()->for($this->organization)->create([
            'name' => 'Original customer',
            'salutation' => 'M.',
            'address' => 'Original street 1',
            'postal_code' => '1000',
            'city' => 'Lausanne',
            'country' => 'CH',
            'country_name' => 'Schweiz',
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->for($this->organization)->create([
            'customer_id' => $this->customer->id,
            'customer_snapshot' => $this->customer->toInvoiceSnapshot(),
            'subtotal' => '100.00',
            'vat_amount' => '8.10',
            'total' => '108.10',
            ...$attributes,
        ])->refresh();
    }

    /** @return array<string, array{InvoiceStatus}> */
    public static function refreshableStatuses(): array
    {
        return [
            'draft' => [InvoiceStatus::Draft],
            'issued' => [InvoiceStatus::Sent],
            'paid' => [InvoiceStatus::Paid],
        ];
    }

    #[DataProvider('refreshableStatuses')]
    public function test_explicit_refresh_updates_only_the_selected_invoice_copy(InvoiceStatus $status): void
    {
        $journalEntry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-10-08',
            'reference' => 'REFRESH-CUSTOMER-TEST',
            'is_posted' => true,
        ]);
        $invoice = $this->invoice(['status' => $status, 'journal_entry_id' => $journalEntry->id]);
        $otherInvoice = $this->invoice();
        $originalSnapshot = $invoice->customer_snapshot;
        $originalInvoice = $invoice->getRawOriginal();
        $originalOtherInvoice = $otherInvoice->getRawOriginal();
        $originalJournalEntry = $journalEntry->refresh()->getRawOriginal();

        $this->customer->update([
            'name' => 'Maël Bächtold',
            'salutation' => 'Monsieur',
            'address' => "Rue de l'Ecluse 66a",
            'postal_code' => '2000',
            'city' => 'Neuchâtel',
            'country_name' => 'Suisse',
        ]);
        $originalContact = $this->customer->refresh()->getRawOriginal();

        $this->assertEquals($originalSnapshot, $invoice->fresh()->customerDetailsForDocument());

        config(['activitylog.enabled' => true]);
        activity()->enableLogging();

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice), [
            'status' => InvoiceStatus::Cancelled->value,
            'total' => '0.00',
        ])->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('success', __('app.invoice_customer_snapshot_refreshed'));

        $invoice->refresh();
        $this->assertEquals($this->customer->toInvoiceSnapshot(), $invoice->customer_snapshot);
        $this->assertSame($status, $invoice->status);

        $refreshedInvoice = $invoice->getRawOriginal();
        unset($originalInvoice['customer_snapshot'], $originalInvoice['updated_at']);
        unset($refreshedInvoice['customer_snapshot'], $refreshedInvoice['updated_at']);

        $this->assertSame($originalInvoice, $refreshedInvoice);
        $this->assertSame($originalOtherInvoice, $otherInvoice->fresh()->getRawOriginal());
        $this->assertSame($originalContact, $this->customer->fresh()->getRawOriginal());
        $this->assertSame($originalJournalEntry, $journalEntry->fresh()->getRawOriginal());

        $activity = Activity::forSubject($invoice)->forEvent('updated')->latest('id')->firstOrFail();
        $this->assertSame((string) $this->user->id, (string) $activity->causer_id);
        $this->assertEquals($originalSnapshot, $activity->attribute_changes->get('old')['customer_snapshot']);
        $this->assertEquals($invoice->customer_snapshot, $activity->attribute_changes->get('attributes')['customer_snapshot']);
    }

    public function test_refresh_replaces_the_whole_copy_including_cleared_values(): void
    {
        $invoice = $this->invoice([
            'customer_snapshot' => [...$this->customer->toInvoiceSnapshot(), 'obsolete' => 'Remove this'],
        ]);
        $this->customer->update([
            'salutation' => null,
            'country_name' => null,
            'email' => null,
            'address' => null,
            'postal_code' => null,
            'city' => null,
            'vat_number' => null,
        ]);

        $updated = app(RefreshInvoiceCustomerSnapshotAction::class)->execute($invoice);

        $this->assertEquals($this->customer->toInvoiceSnapshot(), $updated->customer_snapshot);
        $this->assertNull($updated->customer_snapshot['salutation']);
        $this->assertNull($updated->customer_snapshot['country_name']);
        $this->assertNull($updated->customer_snapshot['address']);
        $this->assertArrayNotHasKey('obsolete', $updated->customer_snapshot);
    }

    public function test_invoice_page_exposes_the_refresh_authorization(): void
    {
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid]);

        $this->actAsOrg()->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRefreshCustomerSnapshot', true));
    }

    public function test_archived_invoice_refuses_refresh_and_hides_the_action(): void
    {
        $invoice = $this->invoice(['archived_at' => now()]);
        $originalSnapshot = $invoice->customer_snapshot;

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertForbidden();
        $this->actAsOrg()->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRefreshCustomerSnapshot', false));

        $this->assertEquals($originalSnapshot, $invoice->fresh()->customer_snapshot);
    }

    public function test_action_rechecks_archival_after_the_invoice_was_loaded(): void
    {
        $invoice = $this->invoice();
        Invoice::whereKey($invoice->id)->update(['archived_at' => now()]);

        $this->expectException(InvalidInvoiceStateException::class);
        $this->expectExceptionMessage(__('app.invoice_customer_snapshot_archived'));

        app(RefreshInvoiceCustomerSnapshotAction::class)->execute($invoice);
    }

    public function test_a_viewer_cannot_refresh_the_invoice_copy(): void
    {
        $invoice = $this->invoice();
        $this->user->syncRoles(['viewer']);

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertForbidden();
        $this->actAsOrg()->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRefreshCustomerSnapshot', false));
    }

    public function test_an_invoice_without_a_linked_customer_cannot_be_refreshed(): void
    {
        $invoice = $this->invoice(['customer_id' => null]);

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertForbidden();
        $this->actAsOrg()->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRefreshCustomerSnapshot', false));
    }

    public function test_a_deleted_customer_cannot_be_used_to_refresh_the_invoice_copy(): void
    {
        $invoice = $this->invoice();
        $this->customer->delete();

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertForbidden();
        $this->assertEquals($invoice->customer_snapshot, $invoice->fresh()->customer_snapshot);

        $this->expectException(ModelNotFoundException::class);
        app(RefreshInvoiceCustomerSnapshotAction::class)->execute($invoice);
    }

    public function test_another_organizations_invoice_cannot_be_refreshed(): void
    {
        $otherOrganization = Organization::factory()->create();
        $invoice = Invoice::factory()->for($otherOrganization)->create();

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertNotFound();
    }

    public function test_a_customer_from_another_organization_cannot_be_copied(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignCustomer = Contact::factory()->for($otherOrganization)->create();
        $invoice = $this->invoice(['customer_id' => $foreignCustomer->id]);

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))->assertForbidden();

        $this->expectException(ModelNotFoundException::class);
        app(RefreshInvoiceCustomerSnapshotAction::class)->execute($invoice);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidReverseChargeCustomers(): array
    {
        return [
            'outside EU' => [['country' => 'CH'], 'invoice_reverse_charge_eu_customer_required'],
            'missing VAT number' => [['vat_number' => null], 'invoice_reverse_charge_vat_number_required'],
        ];
    }

    /** @param  array<string, mixed>  $customerChanges */
    #[DataProvider('invalidReverseChargeCustomers')]
    public function test_invalid_reverse_charge_customer_changes_leave_the_snapshot_unchanged(array $customerChanges, string $errorKey): void
    {
        $this->customer->update(['country' => 'DE', 'vat_number' => 'DE123456789']);
        $invoice = $this->invoice(['tax_treatment' => InvoiceTaxTreatment::ReverseCharge]);
        $originalSnapshot = $invoice->customer_snapshot;
        $this->customer->update($customerChanges);

        $this->actAsOrg()->from(route('invoices.show', $invoice))
            ->post(route('invoices.refreshCustomerSnapshot', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', __('app.'.$errorKey));

        $this->assertEquals($originalSnapshot, $invoice->fresh()->customer_snapshot);
    }

    public function test_valid_reverse_charge_customer_corrections_are_allowed(): void
    {
        $this->customer->update(['country' => 'DE', 'vat_number' => 'DE123456789']);
        $invoice = $this->invoice(['tax_treatment' => InvoiceTaxTreatment::ReverseCharge]);
        $this->customer->update(['name' => 'Updated EU customer', 'country_name' => 'Allemagne']);

        $this->actAsOrg()->post(route('invoices.refreshCustomerSnapshot', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('Updated EU customer', $invoice->fresh()->customer_snapshot['name']);
        $this->assertSame('Allemagne', $invoice->fresh()->customer_snapshot['country_name']);
    }
}
