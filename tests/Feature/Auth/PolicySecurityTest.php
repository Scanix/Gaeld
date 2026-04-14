<?php

namespace Tests\Feature\Auth;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Policies\InvoicePolicy;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Security regression tests covering:
 * - IDOR: BasePolicy cross-org access (Finding #2)
 * - Privilege escalation: Viewer creating credit notes / duplicating invoices (Finding #1)
 * - Business logic: Payment recorded on non-payable invoice status (Finding #6)
 */
class PolicySecurityTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  Finding #1 — Viewer privilege escalation via wrong policy gate
    // ──────────────────────────────────────────────────────────────

    public function test_viewer_cannot_duplicate_invoice_via_http(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($viewer, $this->organization, 'viewer');

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => InvoiceStatus::Sent,
        ]);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($viewer)
            ->post(route('invoices.duplicate', $invoice))
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_credit_note_via_http(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($viewer, $this->organization, 'viewer');

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => InvoiceStatus::Sent,
        ]);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($viewer)
            ->post(route('invoices.creditNote', $invoice))
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Finding #6 — recordPayment allowed on non-payable status
    // ──────────────────────────────────────────────────────────────

    public function test_payment_on_draft_invoice_denied(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => InvoiceStatus::Draft,
        ]);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($this->user)
            ->post(route('invoices.payment', $invoice), [
                'amount' => '100.00',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertForbidden();
    }

    public function test_payment_on_paid_invoice_denied(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => InvoiceStatus::Paid,
        ]);

        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($this->user)
            ->post(route('invoices.payment', $invoice), [
                'amount' => '100.00',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Finding #2 — BasePolicy cross-org IDOR
    // ──────────────────────────────────────────────────────────────

    public function test_base_policy_denies_access_to_cross_org_invoice(): void
    {
        $orgB = Organization::factory()->create();

        $orgBInvoice = Invoice::factory()->create([
            'organization_id' => $orgB->id,
            'status' => InvoiceStatus::Sent,
        ]);

        // Active org is $this->organization (Org A), not Org B
        app(CurrentOrganization::class)->set($this->organization);

        // Policy must deny even if user is resolve-able
        $policy = new InvoicePolicy;
        $this->assertFalse($policy->view($this->user, $orgBInvoice));
        $this->assertFalse($policy->creditNote($this->user, $orgBInvoice));
        $this->assertFalse($policy->duplicate($this->user, $orgBInvoice));
    }

    public function test_cross_org_invoice_http_access_returns_not_found(): void
    {
        $orgB = Organization::factory()->create();

        $orgBInvoice = Invoice::factory()->create([
            'organization_id' => $orgB->id,
            'status' => InvoiceStatus::Sent,
        ]);

        // Active org is Org A — global scope prevents Org B invoice from being found
        app(CurrentOrganization::class)->set($this->organization);

        $this->actingAs($this->user)
            ->get(route('invoices.show', $orgBInvoice))
            ->assertNotFound();
    }
}
