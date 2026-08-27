<?php

namespace Tests\Feature\Payroll;

use App\Domains\Payroll\Models\Employee;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PayrollCrudHttpTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_employee_index_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/payroll/employees')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Employees/Index')
                ->where('payrollWritable', true));
    }

    public function test_viewer_can_view_employees_without_payroll_write_controls(): void
    {
        $viewer = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->organization->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($viewer, $this->organization, 'viewer');

        $this->actingAs($viewer)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/payroll/employees')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Employees/Index')
                ->where('payrollWritable', false));
    }

    public function test_viewer_cannot_open_employee_creation_page(): void
    {
        $viewer = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->organization->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($viewer, $this->organization, 'viewer');

        $this->actingAs($viewer)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/payroll/employees/create')
            ->assertForbidden();
    }

    public function test_employee_create_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/payroll/employees/create')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Payroll/Employees/Create'));
    }

    public function test_employee_store_creates_record(): void
    {
        $this->actingAs($this->user)
            ->post('/payroll/employees', [
                'first_name' => 'Max',
                'last_name' => 'Muster',
                'email' => 'max@example.ch',
                'ahv_number' => '756.1234.5678.90',
                'entry_date' => '2026-01-01',
                'gross_salary' => '6500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'organization_id' => $this->org->id,
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'entry_date' => '2026-01-01',
        ]);
    }

    public function test_employee_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post('/payroll/employees', [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'entry_date', 'gross_salary']);
    }

    public function test_employee_show_loads_salary_slips(): void
    {
        $employee = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Anna',
            'last_name' => 'Klein',
            'entry_date' => '2026-01-01',
            'gross_salary' => '7000.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get("/payroll/employees/{$employee->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Employees/Show')
                ->has('employee')
                ->where('employee.entry_date', $employee->entry_date->toJSON())
            );
    }

    public function test_employee_update_changes_fields(): void
    {
        $employee = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Old',
            'last_name' => 'Name',
            'entry_date' => '2026-01-01',
            'gross_salary' => '5000.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put("/payroll/employees/{$employee->id}", [
                'first_name' => 'New',
                'last_name' => 'Name',
                'entry_date' => '2026-01-01',
                'gross_salary' => '5500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'New',
            'gross_salary' => '5500.00',
        ]);
    }

    public function test_employee_update_without_thirteenth_salary_field_preserves_existing_setting(): void
    {
        $employee = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Thirteen',
            'last_name' => 'Preserved',
            'entry_date' => '2026-01-01',
            'gross_salary' => '5000.00',
            'has_thirteenth_salary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put("/payroll/employees/{$employee->id}", [
                'first_name' => 'Updated',
                'last_name' => 'Preserved',
                'entry_date' => '2026-01-01',
                'gross_salary' => '5500.00',
            ])
            ->assertRedirect();

        $this->assertTrue($employee->fresh()->has_thirteenth_salary);
    }

    public function test_employee_destroy_deletes_record(): void
    {
        $employee = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'To',
            'last_name' => 'Delete',
            'entry_date' => '2026-01-01',
            'gross_salary' => '4000.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->delete("/payroll/employees/{$employee->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_unauthenticated_user_cannot_access_employees(): void
    {
        $this->get('/payroll/employees')->assertRedirect('/login');
    }

    public function test_salary_slips_index_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/payroll/salary-slips')
            ->assertStatus(200);
    }
}
