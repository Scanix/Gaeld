<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Payroll\Contracts\SourceTaxServiceInterface;
use App\Domains\Payroll\Models\SalarySlip;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Posts a salary slip to the accounting ledger (gross salary, deductions, net pay).
 */
class PostPayrollAction
{
    public function __construct(
        private LedgerService $ledger,
        private LedgerQueryService $ledgerQuery,
        private SendSalarySlipEmailAction $sendEmail,
        private SourceTaxServiceInterface $sourceTax,
    ) {}

    public function execute(SalarySlip $slip): SalarySlip
    {
        $this->ensureSourceTaxApplied($slip);

        $deductions = $slip->deductions;
        $orgId = $slip->organization_id;

        $employee = $slip->employee;
        $description = "Salary {$employee->fullName()} — {$slip->period_month}/{$slip->period_year}";

        // Resolve accounts
        $salaryAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::SALARIES);
        $socialChargesAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::SOCIAL_CHARGES_EMPLOYER);
        $bankAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::BANK_CASH);
        $avsAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::AVS_PAYABLE);
        $acAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::AC_PAYABLE);
        $lppAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::LPP_PAYABLE);

        // Calculate aggregated amounts for liability accounts
        $avsTotal = Money::add(
            $deductions['avs_employee'] ?? '0',
            $deductions['avs_employer'] ?? '0',
        );
        // Include AANP in AVS payable if present
        $avsTotal = Money::add($avsTotal, $deductions['aanp_employee'] ?? '0');

        $acTotal = Money::add(
            $deductions['ac_employee'] ?? '0',
            $deductions['ac_employer'] ?? '0',
        );

        $lppTotal = Money::add(
            $deductions['lpp_employee'] ?? '0',
            $deductions['lpp_employer'] ?? '0',
        );
        $sourceTaxAmount = Money::normalize((string) ($deductions['source_tax'] ?? $slip->source_tax_amount ?? '0.00'));

        $lines = [];

        // Debit: Gross salary
        $lines[] = new JournalLineData(
            accountId: (string) $salaryAccount->id,
            debit: $slip->gross_salary,
            credit: '0',
            description: "Gross salary: {$employee->fullName()}",
        );

        // Debit: Employer social charges
        $totalEmployer = $deductions['total_employer'] ?? '0';
        if (Money::isPositive($totalEmployer)) {
            $lines[] = new JournalLineData(
                accountId: (string) $socialChargesAccount->id,
                debit: $totalEmployer,
                credit: '0',
                description: "Employer social charges: {$employee->fullName()}",
            );
        }

        // Credit: Bank (net salary)
        $lines[] = new JournalLineData(
            accountId: (string) $bankAccount->id,
            debit: '0',
            credit: $slip->net_salary,
            description: "Net salary paid: {$employee->fullName()}",
        );

        $reimbursementAmount = (string) ($deductions['reimbursement_amount'] ?? '0.00');
        if (Money::isPositive($reimbursementAmount)) {
            $reimbursementAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::GENERAL_EXPENSE);
            $lines[] = new JournalLineData(
                accountId: (string) $reimbursementAccount->id,
                debit: $reimbursementAmount,
                credit: '0',
                description: "Expense reimbursement: {$employee->fullName()}",
            );
        }

        // Credit: AVS/AI/APG payable
        if (Money::isPositive($avsTotal)) {
            $lines[] = new JournalLineData(
                accountId: (string) $avsAccount->id,
                debit: '0',
                credit: $avsTotal,
                description: 'AVS/AI/APG contributions',
            );
        }

        // Credit: AC payable
        if (Money::isPositive($acTotal)) {
            $lines[] = new JournalLineData(
                accountId: (string) $acAccount->id,
                debit: '0',
                credit: $acTotal,
                description: 'Unemployment insurance (AC)',
            );
        }

        // Credit: LPP payable
        if (Money::isPositive($lppTotal)) {
            $lines[] = new JournalLineData(
                accountId: (string) $lppAccount->id,
                debit: '0',
                credit: $lppTotal,
                description: 'Pension fund (LPP)',
            );
        }

        if (Money::isPositive($sourceTaxAmount)) {
            $sourceTaxAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::WITHHOLDING_TAX_PAYABLE);
            $lines[] = new JournalLineData(
                accountId: (string) $sourceTaxAccount->id,
                debit: '0',
                credit: $sourceTaxAmount,
                description: 'Withholding tax payable',
            );
        }

        // Build a human-friendly reference: PAY-<INITIALS>-<YYYY>-<MM>.
        // Falls back to a short employee-id slug when initials are unavailable.
        $initials = collect((array) preg_split('/\s+/', trim((string) $employee->fullName())))
            ->filter()
            ->map(fn ($p) => strtoupper(mb_substr((string) $p, 0, 1)))
            ->take(3)
            ->implode('');
        $tag = $initials !== '' ? $initials : substr((string) $slip->employee_id, 0, 4);
        $monthPad = str_pad((string) $slip->period_month, 2, '0', STR_PAD_LEFT);

        $entry = new JournalEntryData(
            date: Carbon::create($slip->period_year, $slip->period_month)->endOfMonth()->toDateString(),
            reference: "PAY-{$tag}-{$slip->period_year}-{$monthPad}",
            description: $description,
            lines: $lines,
        );

        $journalEntry = $this->ledger->postEntry($orgId, $entry);

        $slip->update([
            'journal_entry_id' => $journalEntry->id,
            'posted_at' => now(),
        ]);

        $postedSlip = $slip->fresh();
        $this->sendEmail->execute($postedSlip);

        return $postedSlip;
    }

    private function ensureSourceTaxApplied(SalarySlip $slip): void
    {
        if ($slip->source_tax_base === null) {
            $this->sourceTax->applyToSlip($slip, $slip->employee);
        }

        if ($slip->source_tax_base === null) {
            return;
        }

        $deductions = $slip->deductions;
        if (array_key_exists('source_tax', $deductions)) {
            return;
        }

        $sourceTaxAmount = Money::normalize((string) ($slip->source_tax_amount ?? '0.00'));
        $deductions['source_tax'] = $sourceTaxAmount;
        $deductions['net_salary'] = Money::subtract($slip->net_salary, $sourceTaxAmount);
        $slip->forceFill([
            'net_salary' => $deductions['net_salary'],
            'deductions' => $deductions,
        ]);

        if ($slip->exists) {
            $slip->saveQuietly();
        }
    }
}
