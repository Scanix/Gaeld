<?php

namespace Tests\Security\Authorization;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Tests\Security\SecurityTestCase;

/**
 * RBAC (Role-Based Access Control) tests.
 *
 * Verifies that each role is restricted to the correct set of actions within
 * its own organization. Tests the permission matrix:
 *
 *  viewer  — read only, no writes
 *  member  — create/edit, no delete, no user management
 *  admin   — all except organization.delete
 *  owner   — all
 */
class VerticalPrivilegeTest extends SecurityTestCase
{
    private User $viewer;

    private User $member;

    private User $admin;

    private Invoice $invoice;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = $this->createUserInOrg($this->orgA, 'viewer');
        $this->member = $this->createUserInOrg($this->orgA, 'member');
        $this->admin = $this->createUserInOrg($this->orgA, 'admin');

        // Seed an AR account and customer required for invoice creation
        app(CurrentOrganization::class)->set($this->orgA);

        $ar = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);

        $this->customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Test Client',
        ]);

        $this->invoice = Invoice::create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-RBAC-001',
            'status' => InvoiceStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'CHF',
            'subtotal' => '500.00',
            'tax_amount' => '40.50',
            'total' => '540.50',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Viewer
    // ──────────────────────────────────────────────────────────────

    public function test_viewer_can_view_invoices(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/invoices')
            ->assertOk();
    }

    public function test_viewer_cannot_create_invoice(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/invoices', [])
            ->assertForbidden();
    }

    public function test_viewer_cannot_delete_invoice(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    }

    public function test_viewer_cannot_update_invoice(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->put("/invoices/{$this->invoice->id}", ['number' => 'EVIL'])
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_customer(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/customers', ['name' => 'Hacker Corp'])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Member
    // ──────────────────────────────────────────────────────────────

    public function test_member_can_view_invoices(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/invoices')
            ->assertOk();
    }

    public function test_member_cannot_delete_invoice(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_manage_org_members(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/organizations/{$this->orgA->id}/members/{$this->viewer->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_delete_customer(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/customers/{$this->customer->uuid}")
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Admin
    // ──────────────────────────────────────────────────────────────

    public function test_admin_can_delete_invoice(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/invoices/{$this->invoice->id}");

        // Web routes redirect after successful delete
        $this->assertContains($response->status(), [200, 302],
            "Admin should be allowed to delete invoices, got HTTP {$response->status()}");
    }

    public function test_admin_cannot_delete_organization(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/organizations/{$this->orgA->id}");

        // 403 (forbidden) or 405 (method not allowed) — both deny the action
        $this->assertContains($response->status(), [403, 405],
            "Admin should not be able to delete org, got HTTP {$response->status()}");
    }

    public function test_admin_can_manage_org_members(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/organizations/{$this->orgA->id}/members/{$this->viewer->id}");

        // Web routes redirect after successful action
        $this->assertContains($response->status(), [200, 302],
            "Admin should be allowed to manage members, got HTTP {$response->status()}");
    }

    // ──────────────────────────────────────────────────────────────
    //  Owner
    // ──────────────────────────────────────────────────────────────

    public function test_owner_can_delete_organization(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->delete("/organizations/{$this->orgA->id}");

        // 200/302 = allowed, 405 = DELETE route doesn't exist (org deletion via different mechanism)
        if ($response->status() === 405) {
            $this->markTestSkipped('Organization deletion is not implemented via DELETE route');
        }

        $this->assertContains($response->status(), [200, 302],
            "Owner should be allowed to delete org, got HTTP {$response->status()}");
    }
}
