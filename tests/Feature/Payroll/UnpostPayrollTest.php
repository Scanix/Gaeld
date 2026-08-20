<?php

namespace Tests\Feature\Payroll;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Actions\UnpostPayrollAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class UnpostPayrollTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

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
            'entry_date' => '2025-01-01',
            'gross_salary' => '6000.00',
            'is_active' => true,
        ]);
    }

    private function postedSlip(): SalarySlip
    {
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();

        return app(PostPayrollAction::class)->execute($slip);
    }

    public function test_it_unposts_a_slip_and_reverses_the_journal_entry(): void
    {
        $slip = $this->postedSlip();
        $originalRef = $slip->journalEntry->reference;

        $result = app(UnpostPayrollAction::class)->execute($slip);

        $this->assertNull($result->posted_at);
        $this->assertNull($result->journal_entry_id);

        $reversal = JournalEntry::where('organization_id', $this->org->id)
            ->where('reference', 'REV-'.$originalRef)
            ->first();
        $this->assertNotNull($reversal);
        $this->assertTrue($reversal->is_posted);
    }

    public function test_it_refuses_to_unpost_a_draft_slip(): void
    {
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();

        $this->expectException(\DomainException::class);
        app(UnpostPayrollAction::class)->execute($slip);
    }

    public function test_unpost_route_reverts_the_slip(): void
    {
        $slip = $this->postedSlip();

        $response = $this->actAsOrg()->post("/payroll/salary-slips/{$slip->id}/unpost");

        $response->assertRedirect(route('payroll.salarySlips.show', $slip));
        $this->assertNull($slip->fresh()->posted_at);
    }

    public function test_a_draft_slip_can_be_deleted_and_regenerated(): void
    {
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();

        $this->actAsOrg()->delete("/payroll/salary-slips/{$slip->id}")->assertRedirect();
        $this->assertDatabaseMissing('salary_slips', ['id' => $slip->id]);

        // Regenerating for the same period should now succeed (no unique-constraint conflict).
        $this->actAsOrg()->post(route('payroll.run.generate'), [
            'month' => 3,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertSame(1, SalarySlip::where('employee_id', $this->employee->id)
            ->where('period_month', 3)->where('period_year', 2026)->count());
    }

    public function test_a_posted_slip_cannot_be_deleted_directly(): void
    {
        $slip = $this->postedSlip();

        $response = $this->actAsOrg()->delete("/payroll/salary-slips/{$slip->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('salary_slips', ['id' => $slip->id]);
    }
}
