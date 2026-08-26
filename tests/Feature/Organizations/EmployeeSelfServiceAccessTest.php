<?php

namespace Tests\Feature\Organizations;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Services\InvitationService;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class EmployeeSelfServiceAccessTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->employeeUser = User::factory()->create([
            'email' => 'employee@example.test',
            'onboarding_completed_at' => now(),
        ]);
        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => $this->employeeUser->email,
        ]);

        app(OrganizationService::class)->addMember(
            $this->organization,
            $this->employeeUser,
            Role::Employee->value,
        );
    }

    public function test_employee_role_has_only_self_service_permissions(): void
    {
        $this->assertTrue($this->employeeUser->hasPermissionTo(Permission::ExpensesCreate));
        $this->assertTrue($this->employeeUser->hasPermissionTo(Permission::ExpensesViewOwn));
        $this->assertTrue($this->employeeUser->hasPermissionTo(Permission::PayrollSalarySlipsViewOwn));

        $this->assertFalse($this->employeeUser->hasPermissionTo(Permission::ExpensesView));
        $this->assertFalse($this->employeeUser->hasPermissionTo(Permission::PayrollView));
        $this->assertFalse($this->employeeUser->hasPermissionTo(Permission::ReportingView));
        $this->assertFalse($this->employeeUser->hasPermissionTo(Permission::AccountingView));
        $this->assertFalse($this->employeeUser->hasPermissionTo(Permission::OrganizationView));
    }

    public function test_employee_is_linked_to_matching_payroll_record(): void
    {
        $this->assertSame($this->employeeUser->id, $this->employee->refresh()->user_id);
    }

    public function test_employee_invitation_links_the_matching_payroll_record(): void
    {
        Notification::fake();
        $invitedUser = User::factory()->create(['email' => 'invited-employee@example.test']);
        $payrollRecord = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => $invitedUser->email,
        ]);

        $invitation = app(InvitationService::class)->invite(
            $this->organization,
            $invitedUser->email,
            Role::Employee,
            $this->user,
        );

        $this->actingAs($invitedUser);
        app(InvitationService::class)->accept($invitation->plain_token);

        $this->assertSame($invitedUser->id, $payrollRecord->refresh()->user_id);
        $this->assertSame(Role::Employee->value, $this->organization->users()->find($invitedUser->id)?->pivot->role);
    }

    public function test_employee_sees_only_own_posted_salary_slips(): void
    {
        $ownPosted = $this->salarySlip($this->employee, posted: true);
        $this->salarySlip($this->employee, posted: false, month: 2);
        $colleague = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $colleagueSlip = $this->salarySlip($colleague, posted: true, month: 3);

        $response = $this->asEmployee()->get(route('payroll.salarySlips.index'));

        $response->assertOk();
        $slips = $response->viewData('page')['props']['slips']['data'];
        $this->assertSame([$ownPosted->id], array_column($slips, 'id'));

        $this->asEmployee()->get(route('payroll.salarySlips.show', $ownPosted))->assertOk();
        $this->asEmployee()->get(route('payroll.salarySlips.show', $colleagueSlip))->assertForbidden();
    }

    public function test_employee_sees_only_own_expenses(): void
    {
        $own = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employeeUser->id,
        ]);
        $other = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->asEmployee()->get(route('expenses.index'));

        $response->assertOk();
        $expenses = $response->viewData('page')['props']['expenses']['data'];
        $this->assertSame([$own->id], array_column($expenses, 'id'));

        $this->asEmployee()->get(route('expenses.show', $own))->assertOk();
        $this->asEmployee()->get(route('expenses.show', $other))->assertForbidden();
    }

    public function test_employee_is_denied_global_financial_and_settings_routes(): void
    {
        $this->asEmployee()->get('/reports/profit-and-loss')->assertForbidden();
        $this->asEmployee()->get('/settings')->assertForbidden();
        $this->asEmployee()->get('/accounting/journal-entries/create')->assertForbidden();
        $this->asEmployee()->get('/payroll/employees')->assertForbidden();
    }

    public function test_employee_dashboard_redirects_to_own_salary_slips(): void
    {
        $this->asEmployee()->get('/dashboard')
            ->assertRedirect(route('payroll.salarySlips.index'));
    }

    private function asEmployee(): static
    {
        return $this->actingAs($this->employeeUser)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function salarySlip(Employee $employee, bool $posted, int $month = 1): SalarySlip
    {
        return SalarySlip::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'period_month' => $month,
            'period_year' => 2026,
            'gross_salary' => '5000.00',
            'net_salary' => '4300.00',
            'deductions' => [],
            'posted_at' => $posted ? now() : null,
        ]);
    }
}
