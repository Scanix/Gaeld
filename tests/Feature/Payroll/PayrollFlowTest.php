<?php

namespace Tests\Feature\Payroll;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class PayrollFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        // Create required accounts
        foreach ([
            ['code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value],
            ['code' => '5000', 'name' => 'Salaries', 'type' => AccountType::Expense->value],
            ['code' => '5700', 'name' => 'Social Charges', 'type' => AccountType::Expense->value],
            ['code' => '2270', 'name' => 'AVS Payable', 'type' => AccountType::Liability->value],
            ['code' => '2271', 'name' => 'AC Payable', 'type' => AccountType::Liability->value],
            ['code' => '2272', 'name' => 'LPP Payable', 'type' => AccountType::Liability->value],
        ] as $account) {
            Account::create(array_merge($account, ['organization_id' => $this->org->id]));
        }

        $this->employee = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'max@example.com',
            'ahv_number' => '756.1234.5678.90',
            'entry_date' => '2025-01-01',
            'gross_salary' => '6000.00',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_creates_employee_via_route(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('payroll.employees.store'), [
                'first_name' => 'Anna',
                'last_name' => 'Beispiel',
                'email' => 'anna@example.com',
                'entry_date' => '2026-01-01',
                'gross_salary' => '5500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'first_name' => 'Anna',
            'last_name' => 'Beispiel',
            'organization_id' => $this->org->id,
        ]);
    }

    #[Test]
    public function it_generates_salary_slip_with_correct_deductions(): void
    {
        $calculator = app(PayrollCalculator::class);
        $slip = $calculator->calculate($this->employee, 3, 2026);
        $slip->save();

        $this->assertSame('6000.00', $slip->gross_salary);
        $this->assertSame('5136.00', $slip->net_salary);

        $deductions = $slip->deductions;
        $this->assertSame('318.00', $deductions['avs_employee']);
        $this->assertSame('864.00', $deductions['total_employee']);
    }

    #[Test]
    public function it_posts_salary_slip_to_ledger_with_balanced_entry(): void
    {
        $calculator = app(PayrollCalculator::class);
        $slip = $calculator->calculate($this->employee, 3, 2026);
        $slip->save();

        $action = app(PostPayrollAction::class);
        $postedSlip = $action->execute($slip);

        $this->assertNotNull($postedSlip->posted_at);
        $this->assertNotNull($postedSlip->journal_entry_id);

        $journalEntry = JournalEntry::find($postedSlip->journal_entry_id);
        $this->assertTrue($journalEntry->is_posted);
        $this->assertTrue($journalEntry->isBalanced());
    }

    #[Test]
    public function it_prevents_double_posting(): void
    {
        $calculator = app(PayrollCalculator::class);
        $slip = $calculator->calculate($this->employee, 3, 2026);
        $slip->save();

        $action = app(PostPayrollAction::class);
        $action->execute($slip);

        // Attempt via route should fail
        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('payroll.salarySlips.post', $slip))
            ->assertRedirect();

        // Should still only have 1 journal entry
        $this->assertSame(1, JournalEntry::where('reference', 'like', 'PAY-%')->count());
    }

    #[Test]
    public function payroll_run_generates_slips_for_all_active_employees(): void
    {
        $employee2 = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Lisa',
            'last_name' => 'Test',
            'entry_date' => '2025-06-01',
            'gross_salary' => '7000.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('payroll.run.generate'), [
                'month' => 4,
                'year' => 2026,
            ])
            ->assertRedirect();

        $this->assertSame(2, SalarySlip::where('period_month', 4)->where('period_year', 2026)->count());
    }
}
