<?php

namespace Tests\Security\Authorization;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Policies\AccountPolicy;
use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Policies\OrganizationPolicy;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Tests\Security\SecurityTestCase;

/**
 * Tests for the accountant role, audit-log permission, and year-end closing
 * authorization fixes introduced in 2026-03-30 migration.
 */
class PermissionFixesTest extends SecurityTestCase
{
    private User $viewer;

    private User $member;

    private User $accountant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = $this->createUserInOrg($this->orgA, 'viewer');
        $this->member = $this->createUserInOrg($this->orgA, 'member');
        $this->accountant = $this->createUserInOrg($this->orgA, 'accountant');
        $this->admin = $this->createUserInOrg($this->orgA, 'admin');

        app(CurrentOrganization::class)->set($this->orgA);

        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Accountant role exists and has correct permissions
    // ──────────────────────────────────────────────────────────────

    public function test_accountant_role_is_defined(): void
    {
        $this->assertContains('accountant', Role::values());
    }

    public function test_accountant_has_accounting_close_year(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertContains('accounting.close-year', $perms);
    }

    public function test_accountant_has_accounting_delete(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertContains('accounting.delete', $perms);
    }

    public function test_accountant_does_not_have_audit_log_permission(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertNotContains('organization.view-audit-log', $perms);
    }

    public function test_accountant_cannot_delete_invoices(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertNotContains('invoicing.delete', $perms);
    }

    public function test_accountant_cannot_manage_users(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertNotContains('organization.manage-users', $perms);
    }

    public function test_accountant_cannot_delete_organization(): void
    {
        $perms = Role::Accountant->permissionValues();
        $this->assertNotContains('organization.delete', $perms);
    }

    // ──────────────────────────────────────────────────────────────
    //  Member role does NOT have audit-log permission
    // ──────────────────────────────────────────────────────────────

    public function test_member_does_not_have_audit_log_permission(): void
    {
        $perms = Role::Member->permissionValues();
        $this->assertNotContains('organization.view-audit-log', $perms);
    }

    public function test_viewer_does_not_have_audit_log_permission(): void
    {
        $perms = Role::Viewer->permissionValues();
        $this->assertNotContains('organization.view-audit-log', $perms);
    }

    // ──────────────────────────────────────────────────────────────
    //  Year-end closing uses accounting.close-year (not accounting.create)
    // ──────────────────────────────────────────────────────────────

    public function test_account_policy_close_year_requires_permission(): void
    {
        $policy = new AccountPolicy;

        // Owner has all permissions
        $this->assertTrue($policy->closeYear($this->ownerA));

        // Accountant has close-year
        $this->assertTrue($policy->closeYear($this->accountant));

        // Member does NOT have close-year
        $this->assertFalse($policy->closeYear($this->member));

        // Viewer does NOT have close-year
        $this->assertFalse($policy->closeYear($this->viewer));
    }

    public function test_viewer_cannot_access_year_end_closing_page(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/accounting/year-end-closing')
            ->assertForbidden();
    }

    public function test_accountant_can_access_year_end_closing_page(): void
    {
        $this->actingAs($this->accountant)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/accounting/year-end-closing')
            ->assertOk();
    }

    public function test_member_cannot_post_year_end_closing(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/accounting/year-end-closing', [
                'year' => now()->year,
                'closing_date' => now()->toDateString(),
                'reference' => 'TEST',
                'result_account_code' => '9999',
            ])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Activity log uses organization.view-audit-log
    // ──────────────────────────────────────────────────────────────

    public function test_organization_policy_view_audit_log(): void
    {
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->viewAuditLog($this->ownerA, $this->orgA));
        $this->assertTrue($policy->viewAuditLog($this->admin, $this->orgA));
        $this->assertFalse($policy->viewAuditLog($this->accountant, $this->orgA));
        $this->assertFalse($policy->viewAuditLog($this->member, $this->orgA));
        $this->assertFalse($policy->viewAuditLog($this->viewer, $this->orgA));
    }

    public function test_viewer_cannot_access_activity_log(): void
    {
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/settings/activity-log')
            ->assertForbidden();
    }

    public function test_member_cannot_access_activity_log(): void
    {
        $this->actingAs($this->member)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/settings/activity-log')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_activity_log(): void
    {
        $this->actingAs($this->accountant)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/settings/activity-log')
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Legal archives require accounting.view via policy
    // ──────────────────────────────────────────────────────────────

    public function test_viewer_can_access_legal_archives(): void
    {
        // Viewer has accounting.view
        $this->actingAs($this->viewer)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/accounting/archives')
            ->assertOk();
    }

    public function test_accountant_can_access_legal_archives(): void
    {
        $this->actingAs($this->accountant)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->get('/accounting/archives')
            ->assertOk();
    }
}
