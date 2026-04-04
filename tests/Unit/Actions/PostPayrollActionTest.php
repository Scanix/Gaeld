<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PostPayrollActionTest extends TestCase
{
    private LedgerService $ledger;

    private LedgerQueryService $ledgerQuery;

    private PostPayrollAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = Mockery::mock(LedgerService::class);
        $this->ledgerQuery = Mockery::mock(LedgerQueryService::class);
        $this->action = new PostPayrollAction($this->ledger, $this->ledgerQuery);
    }

    public function test_posts_salary_slip_with_no_deductions(): void
    {
        $slip = $this->makeSlip(grossSalary: '5000.00', netSalary: '5000.00', deductions: []);

        $this->stubLedgerAccounts($slip->organization_id);

        $slip->shouldReceive('update')->once()->with(Mockery::on(function (array $data) {
            return isset($data['journal_entry_id'], $data['posted_at']);
        }));
        $slip->shouldReceive('fresh')->once()->andReturnSelf();

        $result = $this->action->execute($slip);

        $this->assertSame($slip, $result);
    }

    public function test_posts_salary_slip_with_all_deductions(): void
    {
        $deductions = [
            'avs_employee' => '200.00',
            'avs_employer' => '200.00',
            'aanp_employee' => '50.00',
            'ac_employee' => '80.00',
            'ac_employer' => '80.00',
            'lpp_employee' => '150.00',
            'lpp_employer' => '150.00',
            'total_employer' => '430.00',
        ];

        $slip = $this->makeSlip(grossSalary: '5000.00', netSalary: '4320.00', deductions: $deductions);

        $this->stubLedgerAccounts($slip->organization_id);

        $slip->shouldReceive('update')->once();
        $slip->shouldReceive('fresh')->once()->andReturnSelf();

        $result = $this->action->execute($slip);

        $this->assertSame($slip, $result);
    }

    public function test_builds_correct_journal_reference(): void
    {
        $slip = $this->makeSlip(
            grossSalary: '3000.00',
            netSalary: '3000.00',
            deductions: [],
            employeeId: 'emp-42',
            periodYear: 2026,
            periodMonth: 3,
        );

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->andReturn($this->makeAccount('1'));

        $this->ledger
            ->shouldReceive('postEntry')
            ->once()
            ->with('org-1', Mockery::on(function ($entry) {
                return $entry->reference === 'PAY-emp-42-2026-3';
            }))
            ->andReturn($this->makeJournalEntry(99));

        $slip->shouldReceive('update')->once();
        $slip->shouldReceive('fresh')->once()->andReturnSelf();

        $this->action->execute($slip);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function makeSlip(
        string $grossSalary,
        string $netSalary,
        array $deductions,
        string $employeeId = 'emp-1',
        int $periodYear = 2026,
        int $periodMonth = 1,
    ): SalarySlip {
        /** @var SalarySlip&MockInterface $slip */
        $slip = Mockery::mock(SalarySlip::class)->makePartial();
        $slip->organization_id = 'org-1';
        $slip->employee_id = $employeeId;
        $slip->period_year = $periodYear;
        $slip->period_month = $periodMonth;
        $slip->gross_salary = $grossSalary;
        $slip->net_salary = $netSalary;
        $slip->deductions = $deductions;

        $employee = Mockery::mock(Employee::class)->makePartial();
        $employee->shouldReceive('fullName')->andReturn('John Doe');
        $slip->shouldReceive('getAttribute')->with('employee')->andReturn($employee);
        $slip->employee = $employee;

        return $slip;
    }

    private function stubLedgerAccounts(string $orgId): void
    {
        $account = $this->makeAccount('100');

        $this->ledgerQuery->shouldReceive('resolveAccount')->andReturn($account);

        $journalEntry = $this->makeJournalEntry(1);

        $this->ledger->shouldReceive('postEntry')->once()->andReturn($journalEntry);
    }

    private function makeAccount(string $id): Account
    {
        /** @var Account&MockInterface $account */
        $account = Mockery::mock(Account::class)->makePartial();
        $account->id = $id;
        $account->code = AccountCode::SALARIES;

        return $account;
    }

    private function makeJournalEntry(int $id): JournalEntry
    {
        /** @var JournalEntry&MockInterface $entry */
        $entry = Mockery::mock(JournalEntry::class)->makePartial();
        $entry->id = $id;

        return $entry;
    }
}
