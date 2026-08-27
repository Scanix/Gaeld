<?php

namespace Tests\Feature\Payroll;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Actions\GenerateSalaryCertificateAction;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Contracts\SourceTaxServiceInterface;
use App\Domains\Payroll\Jobs\SendSalarySlipEmailJob;
use App\Domains\Payroll\Mail\SalarySlipReadyMail;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Payroll\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class PayrollFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();

        // Create required accounts
        foreach ([
            ['code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value],
            ['code' => '5000', 'name' => 'Salaries', 'type' => AccountType::Expense->value],
            ['code' => '5700', 'name' => 'Social Charges', 'type' => AccountType::Expense->value],
            ['code' => '6530', 'name' => 'General Expense', 'type' => AccountType::Expense->value],
            ['code' => '2270', 'name' => 'AVS Payable', 'type' => AccountType::Liability->value],
            ['code' => '2271', 'name' => 'AC Payable', 'type' => AccountType::Liability->value],
            ['code' => '2272', 'name' => 'LPP Payable', 'type' => AccountType::Liability->value],
            ['code' => '2273', 'name' => 'Withholding Tax Payable', 'type' => AccountType::Liability->value],
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

        /** @var array{avs_employee: string, total_employee: string} $deductions */
        $deductions = $slip->deductions;
        $this->assertSame('318.00', $deductions['avs_employee']);
        $this->assertSame('864.00', $deductions['total_employee']);
    }

    #[Test]
    public function it_adds_a_thirteenth_salary_in_december_when_enabled(): void
    {
        $this->employee->update(['has_thirteenth_salary' => true]);

        $slip = app(PayrollCalculator::class)->calculate($this->employee->fresh(), 12, 2026);

        $this->assertSame('12000.00', $slip->gross_salary);
        $this->assertSame('6000.00', $slip->adjustments['thirteenth_salary']);
        $this->assertSame('10272.00', $slip->net_salary);
    }

    #[Test]
    public function it_applies_unpaid_leave_and_adds_a_non_taxable_reimbursement(): void
    {
        $slip = app(PayrollCalculator::class)->calculate(
            $this->employee,
            3,
            2026,
            unpaidLeaveDays: 5,
            reimbursementAmount: '100.00',
        );

        $this->assertSame('5032.26', $slip->gross_salary);
        $this->assertSame('967.74', $slip->adjustments['unpaid_leave_amount']);
        $this->assertSame(5, $slip->adjustments['unpaid_leave_days']);
        $this->assertSame('100.00', $slip->adjustments['reimbursement_amount']);
        $this->assertSame('4407.62', $slip->net_salary);
    }

    #[Test]
    public function it_posts_reimbursement_with_a_balanced_ledger_entry(): void
    {
        $slip = app(PayrollCalculator::class)->calculate(
            $this->employee,
            3,
            2026,
            reimbursementAmount: '125.50',
        );
        $slip->save();

        $postedSlip = app(PostPayrollAction::class)->execute($slip);

        $this->assertTrue($postedSlip->journalEntry->isBalanced());
        $this->assertSame(
            125.5,
            $postedSlip->journalEntry->lines
                ->filter(fn ($line) => str_contains((string) $line->description, 'reimbursement'))
                ->sum(fn ($line) => (float) $line->debit),
        );
    }

    #[Test]
    public function payroll_preview_matches_the_generated_salary_slip(): void
    {
        $preview = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson(route('payroll.run.preview'), [
                'employee_ids' => [$this->employee->id],
                'month' => 3,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.employee_id', $this->employee->id)
            ->json('data.0');

        $this->assertDatabaseCount('salary_slips', 0);

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson(route('payroll.run.generate'), [
                'employee_ids' => [$this->employee->id],
                'month' => 3,
                'year' => 2026,
            ])
            ->assertOk();

        $slip = SalarySlip::query()->where('employee_id', $this->employee->id)->firstOrFail();

        $this->assertSame($slip->gross_salary, $preview['gross_salary']);
        $this->assertSame($slip->net_salary, $preview['net_salary']);
        $this->assertSame($slip->deductions, $preview['deductions']);
    }

    #[Test]
    public function payroll_preview_includes_source_tax_in_the_net_salary(): void
    {
        $sourceTax = Mockery::mock(SourceTaxServiceInterface::class);
        $sourceTax->shouldReceive('applyToSlip')->once()->andReturnUsing(
            function (SalarySlip $slip, Employee $employee): void {
                $slip->forceFill([
                    'source_tax_base' => '6000.00',
                    'source_tax_rate' => '8.500000',
                    'source_tax_amount' => '510.00',
                ]);
            },
        );
        $this->app->instance(SourceTaxServiceInterface::class, $sourceTax);

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson(route('payroll.run.preview'), [
                'employee_ids' => [$this->employee->id],
                'month' => 3,
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.deductions.source_tax', '510.00')
            ->assertJsonPath('data.0.net_salary', '4626.00');
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
    public function it_sends_one_salary_slip_email_after_posting(): void
    {
        Mail::fake();
        $this->employee->update(['email' => 'max@example.com']);

        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson(route('payroll.salarySlips.post', $slip))
            ->assertOk();

        Mail::assertSent(SalarySlipReadyMail::class, function (SalarySlipReadyMail $mail) use ($slip): bool {
            return $mail->slip->is($slip);
        });

        $sentMail = Mail::sent(SalarySlipReadyMail::class)->first();
        $this->assertCount(1, $sentMail->attachments());
        $this->assertNotNull($slip->fresh()->email_sent_at);
    }

    #[Test]
    public function it_does_not_send_a_salary_slip_email_twice(): void
    {
        Mail::fake();
        $this->employee->update(['email' => 'max@example.com']);

        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();
        $slip->update(['posted_at' => now()]);

        $job = new SendSalarySlipEmailJob((string) $slip->id, (string) $slip->organization_id);
        $job->handle();
        $job->handle();

        Mail::assertSentCount(1);
        $this->assertNotNull($slip->fresh()->email_sent_at);
    }

    #[Test]
    public function it_uses_the_posting_snapshot_for_the_salary_slip_email(): void
    {
        Mail::fake();
        $this->employee->update(['email' => 'max@example.com']);

        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();
        $slip->update(['posted_at' => now()]);
        $this->employee->update(['first_name' => 'Changed', 'last_name' => 'Later']);

        (new SendSalarySlipEmailJob((string) $slip->id, (string) $slip->organization_id))->handle();

        $mail = Mail::sent(SalarySlipReadyMail::class)->first();
        $this->assertSame('Max', $mail->content()->with['employeeSnapshot']['first_name']);
        $this->assertSame('Muster', $mail->content()->with['employeeSnapshot']['last_name']);
    }

    #[Test]
    public function it_uses_the_posting_snapshot_for_a_direct_salary_slip_pdf(): void
    {
        $this->employee->update(['email' => 'max@example.com']);
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();
        $this->employee->update(['first_name' => 'Changed', 'last_name' => 'Later']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get(route('payroll.salarySlips.pdf', $slip));

        $response->assertOk();
        $this->assertStringContainsString('salary-slip-Muster-2026-3.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertArrayNotHasKey('employee_snapshot', $slip->fresh()->toArray());
    }

    #[Test]
    public function it_does_not_expose_the_employee_ahv_number_in_the_salary_snapshot(): void
    {
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);

        $this->assertNotSame($this->employee->ahv_number, $slip->employee_snapshot['ahv_number']);
        $this->assertTrue($slip->employee_snapshot['ahv_number_encrypted']);
        $this->assertSame($this->employee->ahv_number, $slip->employeeDocumentData()['ahv_number']);
    }

    #[Test]
    public function it_does_not_email_a_salary_slip_that_is_no_longer_posted(): void
    {
        Mail::fake();
        $this->employee->update(['email' => 'max@example.com']);
        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();

        (new SendSalarySlipEmailJob((string) $slip->id, (string) $slip->organization_id))->handle();

        Mail::assertNothingSent();
        $this->assertNull($slip->fresh()->email_sent_at);
    }

    #[Test]
    public function it_rejects_unpaid_leave_days_beyond_the_selected_month(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson(route('payroll.run.preview'), [
                'employee_ids' => [$this->employee->id],
                'month' => 2,
                'year' => 2026,
                'adjustments' => [[
                    'employee_id' => $this->employee->id,
                    'unpaid_leave_days' => 29,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustments.0.unpaid_leave_days']);
    }

    #[Test]
    public function it_rejects_negative_unpaid_leave_days_in_the_calculator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PayrollCalculator::class)->calculate($this->employee, 3, 2026, unpaidLeaveDays: -1);
    }

    #[Test]
    public function it_rounds_unpaid_leave_amounts_to_cents(): void
    {
        $this->employee->update(['gross_salary' => '1000.00']);

        $slip = app(PayrollCalculator::class)->calculate($this->employee->fresh(), 3, 2026, unpaidLeaveDays: 1);

        $this->assertSame('32.26', $slip->adjustments['unpaid_leave_amount']);
        $this->assertSame('967.74', $slip->gross_salary);
    }

    #[Test]
    public function it_rejects_reimbursements_with_fractional_cents(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PayrollCalculator::class)->calculate($this->employee, 3, 2026, reimbursementAmount: '10.999');
    }

    #[Test]
    public function it_returns_no_salary_before_entry_or_after_exit(): void
    {
        $this->employee->update([
            'entry_date' => '2026-06-15',
            'exit_date' => '2026-10-10',
        ]);

        $beforeEntry = app(PayrollCalculator::class)->calculate($this->employee->fresh(), 5, 2026);
        $afterExit = app(PayrollCalculator::class)->calculate($this->employee->fresh(), 11, 2026);
        $entryMonth = app(PayrollCalculator::class)->calculate($this->employee->fresh(), 6, 2026);

        $this->assertSame('0.00', $beforeEntry->gross_salary);
        $this->assertSame('0.00', $afterExit->gross_salary);
        $this->assertSame('3200.00', $entryMonth->gross_salary);
    }

    #[Test]
    public function it_builds_an_annual_certificate_from_posted_slips_only(): void
    {
        Mail::fake();

        $postedSlip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $postedSlip->save();
        app(PostPayrollAction::class)->execute($postedSlip);

        $draftSlip = app(PayrollCalculator::class)->calculate($this->employee, 4, 2026);
        $draftSlip->save();

        $certificate = app(GenerateSalaryCertificateAction::class)->execute($this->employee, 2026);

        $this->assertSame(1, $certificate['months_covered']);
        $this->assertSame('6000.00', $certificate['gross_salary']);
        $this->assertSame('5136.00', $certificate['net_salary']);
    }

    #[Test]
    public function it_keeps_reimbursements_separate_from_annual_salary_net(): void
    {
        Mail::fake();

        $slip = app(PayrollCalculator::class)->calculate(
            $this->employee,
            3,
            2026,
            reimbursementAmount: '100.00',
        );
        $slip->save();
        app(PostPayrollAction::class)->execute($slip);

        $certificate = app(GenerateSalaryCertificateAction::class)->execute($this->employee, 2026);

        $this->assertSame('5136.00', $certificate['net_salary']);
        $this->assertSame('100.00', $certificate['reimbursements']);
        $this->assertSame('5236.00', $certificate['total_paid']);
    }

    #[Test]
    public function it_downloads_the_annual_salary_certificate_as_a_pdf(): void
    {
        Mail::fake();

        $slip = app(PayrollCalculator::class)->calculate($this->employee, 3, 2026);
        $slip->save();
        app(PostPayrollAction::class)->execute($slip);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get(route('payroll.salaryCertificate.download', [
                'employee' => $this->employee,
                'year' => 2026,
            ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    #[Test]
    public function it_posts_payroll_after_a_closed_long_fiscal_year(): void
    {
        FiscalYear::factory()->for($this->org)->create([
            'name' => '2024-2025 Long Year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Closed,
        ]);
        FiscalYear::factory()->for($this->org)->create([
            'name' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        $slip = app(PayrollCalculator::class)->calculate($this->employee, 7, 2025);
        $slip->save();

        $postedSlip = app(PostPayrollAction::class)->execute($slip);

        $this->assertNotNull($postedSlip->journal_entry_id);
        $this->assertTrue(JournalEntry::findOrFail($postedSlip->journal_entry_id)->is_posted);
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

    /**
     * Regression test: SalarySlipController used to authorize every action
     * against $slip->employee (EmployeePolicy), which has no archived_at
     * guard, so the archived-record rule embedded in SalarySlipPolicy was
     * dead code from the HTTP layer's perspective. It's now authorized
     * against the SalarySlip itself.
     */
    #[Test]
    public function archived_salary_slip_cannot_be_posted_via_route(): void
    {
        $calculator = app(PayrollCalculator::class);
        $slip = $calculator->calculate($this->employee, 3, 2026);
        $slip->archived_at = now();
        $slip->save();

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('payroll.salarySlips.post', $slip))
            ->assertForbidden();
    }
}
