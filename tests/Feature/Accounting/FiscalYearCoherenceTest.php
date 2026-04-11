<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\PostSocialChargesAction;
use App\Domains\Accounting\Actions\PostVatSettlementAction;
use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEvent;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Invoicing\Jobs\GenerateRecurringInvoicesJob;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use App\Domains\Payroll\Actions\PostPayrollAction;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Simulates a complete 2025 fiscal year across all accounting domains:
 *
 *  Phase A  – 4 client invoices (3 paid, 1 overdue)
 *  Phase B  – monthly recurring invoice job (Carbon mocked to 2025-06-01)
 *  Phase C  – 3 supplier expense entries
 *  Phase D  – payroll for 2 employees × 3 months (Carbon mocked per month)
 *  Phase E  – fixed-asset depreciation for 12 months
 *  Phase F  – 4 quarterly VAT settlements
 *  Phase G  – 4 quarterly social-charges postings
 *  Phase H  – overdue payment-reminder job
 *  Phase I  – year-end closing (journal entry + fiscal year locked + 2026 opening balances)
 *  Phase J  – coherence assertions (double-entry invariant, P&L zeroed, audit trail, etc.)
 */
class FiscalYearCoherenceTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    // ── Accounts ──────────────────────────────────────────────────
    private Account $bank;          // 1020

    private Account $ar;            // 1100

    private Account $vatInput;      // 1170

    private Account $fixedAssetAcct; // 1500

    private Account $accumDepr;     // 1509

    private Account $ap;            // 2000

    private Account $vatOutput;     // 2200

    private Account $vatPayable;    // 2201

    private Account $avsPayable;    // 2270

    private Account $acPayable;     // 2271

    private Account $lppPayable;    // 2272

    private Account $shareCapital;  // 2800

    private Account $annualResult;  // 2900

    private Account $revenue;       // 3000

    private Account $rounding;      // 3900

    private Account $salaries;      // 5000

    private Account $socialCharges; // 5700

    private Account $generalExpenses; // 6530

    private Account $deprExpense;   // 6800

    private Account $openingBalance; // 9000

    // ── Invoicing ─────────────────────────────────────────────────
    private VatRate $vatNormal;

    private Customer $customer;

    // ── Payroll ───────────────────────────────────────────────────
    private Employee $jean;

    private Employee $marie;

    // ── Fixed asset ───────────────────────────────────────────────
    private FixedAsset $laptop;

    // ── Recurring template ────────────────────────────────────────
    private RecurringInvoice $recurringTemplate;

    // ─────────────────────────────────────────────────────────────
    //  Boot
    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->createAccounts();
        $this->createInvoicingFixtures();
        $this->createPayrollFixtures();
        $this->createAssetFixtures();
        $this->createRecurringTemplate();
    }

    // ─────────────────────────────────────────────────────────────
    //  Single test: the full fiscal year
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function full_fiscal_year_2025_data_is_coherent(): void
    {
        Mail::fake();

        // ── Phase A: client invoices ─────────────────────────────
        $overdueInvoice = $this->runPhaseA_ClientInvoices();

        // ── Phase B: recurring invoice job ──────────────────────
        $this->runPhaseB_RecurringInvoiceJob();

        // ── Phase C: supplier expenses ───────────────────────────
        $this->runPhaseC_SupplierExpenses();

        // ── Phase D: payroll ─────────────────────────────────────
        $this->runPhaseD_Payroll();

        // ── Phase E: fixed-asset depreciation ────────────────────
        $this->runPhaseE_AssetDepreciation();

        // ── Phase F: VAT settlements ─────────────────────────────
        $this->runPhaseF_VatSettlements();

        // ── Phase G: social charges ───────────────────────────────
        $this->runPhaseG_SocialCharges();

        // ── Phase H: payment-reminder job ────────────────────────
        // Reset clock so the overdue invoice (due 2025-11-01) is genuinely past-due.
        Carbon::setTestNow(null);
        $this->runPhaseH_PaymentReminders($overdueInvoice);

        // ── Phase I: year-end closing ─────────────────────────────
        $this->runPhaseI_YearEndClosing();

        // ── Phase J: coherence assertions ─────────────────────────
        $this->assertPhaseJ_Coherence($overdueInvoice);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase A – Client invoices
    // ─────────────────────────────────────────────────────────────

    /** @return Invoice  The overdue (unpaid) invoice */
    private function runPhaseA_ClientInvoices(): Invoice
    {
        $finalize = app(FinalizeInvoiceAction::class);
        $accounting = app(InvoiceAccountingService::class);

        // Q1 – January, paid February
        $inv001 = $this->makeInvoice('INV-2025-001', '2025-01-15', '2025-02-15', '5000.00');
        $finalize->execute($inv001);
        $accounting->recordPayment($inv001->fresh(), new RecordPaymentData(
            amount: $inv001->fresh()->amountDue(),
            paymentDate: '2025-02-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        // Q2 – April, paid May
        $inv003 = $this->makeInvoice('INV-2025-003', '2025-04-10', '2025-05-10', '8500.00');
        $finalize->execute($inv003);
        $accounting->recordPayment($inv003->fresh(), new RecordPaymentData(
            amount: $inv003->fresh()->amountDue(),
            paymentDate: '2025-05-05',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        // Q3 – August, paid September (needed so Q3 VAT report is non-zero)
        $inv005 = $this->makeInvoice('INV-2025-005', '2025-08-01', '2025-09-01', '3200.00');
        $finalize->execute($inv005);
        $accounting->recordPayment($inv005->fresh(), new RecordPaymentData(
            amount: $inv005->fresh()->amountDue(),
            paymentDate: '2025-09-10',
            paymentMethod: PaymentMethod::Bank,
            reference: null,
        ));

        // Q4 – October, NOT paid → becomes overdue
        $inv007 = $this->makeInvoice('INV-2025-007', '2025-10-01', '2025-11-01', '1800.00');
        $finalize->execute($inv007);

        return $inv007->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase B – Recurring invoice job
    // ─────────────────────────────────────────────────────────────

    private function runPhaseB_RecurringInvoiceJob(): void
    {
        // Mock clock to the recurring invoice's scheduled date.
        Carbon::setTestNow('2025-06-01');

        app()->call([app(GenerateRecurringInvoicesJob::class), 'handle']);

        Carbon::setTestNow(null);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase C – Supplier expenses (posted directly to ledger)
    // ─────────────────────────────────────────────────────────────

    private function runPhaseC_SupplierExpenses(): void
    {
        $ledger = app(LedgerService::class);
        $orgId = $this->org->id;

        foreach ([
            ['EXP-2025-ADOBE', '2025-03-31', '65.45', 'Adobe CC – Q1'],
            ['EXP-2025-MSFT',  '2025-06-30', '12.71', 'Microsoft 365 – Jun'],
            ['EXP-2025-RENT',  '2025-09-30', '385.00', 'Office rent – Sep'],
        ] as [$ref, $date, $amount, $desc]) {
            $ledger->postEntry($orgId, new JournalEntryData(
                date: $date,
                reference: $ref,
                description: $desc,
                lines: [
                    new JournalLineData(
                        accountId: (string) $this->generalExpenses->id,
                        debit: $amount,
                        credit: '0.00',
                        description: $desc,
                    ),
                    new JournalLineData(
                        accountId: (string) $this->ap->id,
                        debit: '0.00',
                        credit: $amount,
                        description: $desc,
                    ),
                ],
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase D – Payroll (Carbon mocked per posting month)
    // ─────────────────────────────────────────────────────────────

    private function runPhaseD_Payroll(): void
    {
        $calculator = app(PayrollCalculator::class);
        $postAction = app(PostPayrollAction::class);

        // 3 months × 2 employees; PostPayrollAction now uses period end-of-month as the entry date.
        foreach ([1, 7, 12] as $month) {
            foreach ([$this->jean, $this->marie] as $employee) {
                $slip = $calculator->calculate($employee, $month, 2025);
                $slip->save();
                $postAction->execute($slip);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase E – Fixed-asset depreciation (12 months of 2025)
    // ─────────────────────────────────────────────────────────────

    private function runPhaseE_AssetDepreciation(): void
    {
        $action = app(DepreciateAssetAction::class);

        for ($month = 1; $month <= 12; $month++) {
            // Refresh to get the latest totalDepreciated() after each entry.
            $this->laptop->refresh();
            $action->execute($this->laptop, Carbon::create(2025, $month, 28));
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase F – Quarterly VAT settlements
    // ─────────────────────────────────────────────────────────────

    private function runPhaseF_VatSettlements(): void
    {
        $action = app(PostVatSettlementAction::class);
        $orgId = $this->org->id;

        foreach ([
            ['2025-01-01', '2025-03-31'],
            ['2025-04-01', '2025-06-30'],
            ['2025-07-01', '2025-09-30'],
            ['2025-10-01', '2025-12-31'],
        ] as [$from, $to]) {
            $action->execute($orgId, $from, $to);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase G – Quarterly social-charges postings
    // ─────────────────────────────────────────────────────────────

    private function runPhaseG_SocialCharges(): void
    {
        $action = app(PostSocialChargesAction::class);
        $orgId = $this->org->id;

        foreach ([
            ['2025-03-31', '1200.00', 'AVS paiement Q1 2025'],
            ['2025-06-30', '1200.00', 'AVS paiement Q2 2025'],
            ['2025-09-30', '1200.00', 'AVS paiement Q3 2025'],
            ['2025-12-31', '1200.00', 'AVS paiement Q4 2025'],
        ] as [$date, $amount, $desc]) {
            $action->execute($orgId, $amount, $desc, $date);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase H – Overdue payment-reminder job
    // ─────────────────────────────────────────────────────────────

    private function runPhaseH_PaymentReminders(Invoice $overdueInvoice): void
    {
        // The job uses withoutGlobalScope so it finds invoices across all orgs;
        // with RefreshDatabase there is exactly one overdue invoice (INV-2025-007).
        app()->call([app(SendPaymentRemindersJob::class), 'handle']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase I – Year-end closing
    // ─────────────────────────────────────────────────────────────

    private function runPhaseI_YearEndClosing(): void
    {
        $orgId = $this->org->id;

        [$income, $expenses] = app(ClosingAccountsService::class)->compute($orgId, '2025-01-01', '2025-12-31');

        $this->assertNotEmpty($income, 'No revenue accounts found for 2025 — check invoice finalization');
        $this->assertNotEmpty($expenses, 'No expense accounts found for 2025 — check payroll/depreciation');

        app(YearEndClosingAction::class)->execute(
            orgId: $orgId,
            year: 2025,
            income: $income,
            expenses: $expenses,
            resultAccount: $this->annualResult,
            closingDate: '2025-12-31',
            reference: 'YE-2025',
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase J – Coherence assertions
    // ─────────────────────────────────────────────────────────────

    private function assertPhaseJ_Coherence(Invoice $overdueInvoice): void
    {
        $orgId = $this->org->id;

        // ── J1: Global double-entry invariant ───────────────────
        $totalDebit = TransactionLine::whereHas(
            'journalEntry',
            fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true)
        )->sum('debit');

        $totalCredit = TransactionLine::whereHas(
            'journalEntry',
            fn ($q) => $q->where('organization_id', $orgId)->where('is_posted', true)
        )->sum('credit');

        $this->assertSame(
            0,
            bccomp((string) $totalDebit, (string) $totalCredit, 2),
            "Global debit ({$totalDebit}) ≠ global credit ({$totalCredit}): double-entry violated"
        );

        // ── J2: Every individual posted entry is balanced ────────
        $unbalanced = JournalEntry::where('organization_id', $orgId)
            ->where('is_posted', true)
            ->get()
            ->reject(fn (JournalEntry $je) => $je->isBalanced());

        $this->assertCount(
            0,
            $unbalanced,
            'Unbalanced entries: '.$unbalanced->pluck('reference')->implode(', ')
        );

        // ── J3: 2025 P&L accounts are zeroed after closing ───────
        [$incomeAfter, $expensesAfter] = app(ClosingAccountsService::class)->compute($orgId, '2025-01-01', '2025-12-31');

        $this->assertEmpty(
            $incomeAfter,
            'Revenue accounts still have non-zero 2025 P&L balance after closing: '
            .collect($incomeAfter)->pluck('code')->implode(', ')
        );

        $this->assertEmpty(
            $expensesAfter,
            'Expense accounts still have non-zero 2025 P&L balance after closing: '
            .collect($expensesAfter)->pluck('code')->implode(', ')
        );

        // ── J4: Result account (2900) holds the net income ───────
        $resultDebit = (string) TransactionLine::where('account_id', $this->annualResult->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true))
            ->sum('debit');
        $resultCredit = (string) TransactionLine::where('account_id', $this->annualResult->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true))
            ->sum('credit');

        $netOnResult = bcsub($resultCredit, $resultDebit, 2);
        $this->assertNotSame(
            '0.00',
            $netOnResult,
            'Result account (2900) should carry the net P&L balance after year-end closing; got 0.00'
        );

        // ── J5: Closing journal entry exists and is posted ───────
        $closingEntry = JournalEntry::where('organization_id', $orgId)
            ->where('reference', 'YE-2025')
            ->first();

        $this->assertNotNull($closingEntry, 'Year-end closing entry YE-2025 not found');
        $this->assertTrue($closingEntry->is_posted);
        $this->assertSame('2025-12-31', $closingEntry->date->toDateString());

        // ── J6: Opening balances for 2026 exist ──────────────────
        $openingEntry = JournalEntry::where('organization_id', $orgId)
            ->where('reference', 'OPENING-2026')
            ->first();

        $this->assertNotNull($openingEntry, 'Opening balance entry OPENING-2026 not found');
        $this->assertTrue($openingEntry->is_posted);
        $this->assertTrue($openingEntry->isBalanced());

        // ── J7: Fiscal year 2025 is now locked ───────────────────
        $this->assertTrue(
            $this->org->fresh()->isFiscalYearClosed(2025),
            'Organization::isFiscalYearClosed(2025) should be true after year-end closing'
        );

        // ── J8: Posting to closed year throws FiscalYearClosedException ──
        try {
            app(LedgerService::class)->postEntry($orgId, new JournalEntryData(
                date: '2025-06-15',
                reference: 'BLOCKED-TEST-'.uniqid(),
                description: 'Should be blocked by fiscal year guard',
                lines: [
                    new JournalLineData(
                        accountId: (string) $this->bank->id,
                        debit: '100.00',
                        credit: '0.00',
                        description: 'test',
                    ),
                    new JournalLineData(
                        accountId: (string) $this->revenue->id,
                        debit: '0.00',
                        credit: '100.00',
                        description: 'test',
                    ),
                ],
            ));
            $this->fail('Expected FiscalYearClosedException was not thrown');
        } catch (FiscalYearClosedException $e) {
            $this->assertStringContainsString('2025', $e->getMessage());
        }

        // ── J9: Audit trail – JournalEvent count ─────────────────
        //  Expected minimum breakdown:
        //    Phase A : 4 finalize + 3 payments            = 7
        //    Phase C : 3 expense entries                  = 3
        //    Phase D : 6 payroll slips                    = 6
        //    Phase E : 12 depreciation entries            = 12
        //    Phase F : 4 VAT settlements                  = 4
        //    Phase G : 4 social charges                   = 4
        //    Phase I : 1 closing + 1 opening               = 2
        //  ─────────────────────────────────────────────────
        //    Min total                                    = 38
        //  (Phase B generates a draft invoice, NOT a posted JE)
        $postedEventCount = JournalEvent::whereHas(
            'journalEntry',
            fn ($q) => $q->where('organization_id', $orgId)
        )->where('event_type', 'posted')->count();

        $this->assertGreaterThanOrEqual(38, $postedEventCount,
            "Expected ≥38 'posted' JournalEvents, got {$postedEventCount}"
        );

        // ── J10: No unexpected reversal events ───────────────────
        $reversalCount = JournalEvent::whereHas(
            'journalEntry',
            fn ($q) => $q->where('organization_id', $orgId)
        )->where('event_type', 'reversed')->count();

        $this->assertSame(0, $reversalCount,
            "Expected 0 reversal events; found {$reversalCount}"
        );

        // ── J11: Recurring invoice was generated ─────────────────
        $totalInvoices = Invoice::where('organization_id', $orgId)->count();
        // 4 manual + 1 from recurring job = 5
        $this->assertSame(5, $totalInvoices,
            "Expected 5 invoices (4 manual + 1 from recurring job), found {$totalInvoices}"
        );

        $this->recurringTemplate->refresh();
        $this->assertSame(
            '2025-07-01',
            $this->recurringTemplate->next_issue_date->toDateString(),
            'Recurring invoice next_issue_date should advance to 2025-07-01 after generation'
        );

        // ── J12: Payment reminder sent for overdue invoice ───────
        Mail::assertSent(InvoiceReminderMail::class);

        $this->assertNotNull(
            $overdueInvoice->fresh()->last_reminded_at,
            'Overdue invoice last_reminded_at should be set after reminder job'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Create a draft invoice and return it. */
    private function makeInvoice(
        string $number,
        string $issueDate,
        string $dueDate,
        string $unitPrice,
    ): Invoice {
        return app(CreateInvoiceAction::class)->execute(new CreateInvoiceData(
            organizationId: $this->org->id,
            customerId: (string) $this->customer->id,
            number: $number,
            issueDate: $issueDate,
            dueDate: $dueDate,
            currency: 'CHF',
            notes: null,
            paymentTerms: null,
            lines: [
                new InvoiceLineData(
                    description: 'Prestation de services',
                    quantity: '1',
                    unitPrice: $unitPrice,
                    vatRateId: (string) $this->vatNormal->id,
                ),
            ],
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  Fixture factories
    // ─────────────────────────────────────────────────────────────

    private function createAccounts(): void
    {
        $defs = [
            ['1020', 'Bank CHF',                    AccountType::Asset],
            ['1100', 'Accounts Receivable',         AccountType::Asset],
            ['1170', 'VAT Input',                   AccountType::Asset],
            ['1500', 'Fixed Assets',                AccountType::Asset],
            ['1509', 'Accumulated Depreciation',    AccountType::Asset],
            ['2000', 'Accounts Payable',            AccountType::Liability],
            ['2200', 'VAT Output',                  AccountType::Liability],
            ['2201', 'VAT Payable AFC',             AccountType::Liability],
            ['2270', 'AVS Payable',                 AccountType::Liability],
            ['2271', 'AC Payable',                  AccountType::Liability],
            ['2272', 'LPP Payable',                 AccountType::Liability],
            ['2800', 'Share Capital',               AccountType::Equity],
            ['2900', 'Annual Result',               AccountType::Equity],
            ['3000', 'Revenue – Services',          AccountType::Revenue],
            ['3900', 'Rounding Difference',         AccountType::Revenue],
            ['5000', 'Salaries',                    AccountType::Expense],
            ['5700', 'Social Security Charges',     AccountType::Expense],
            ['6530', 'General Expenses',            AccountType::Expense],
            ['6800', 'Depreciation Expense',        AccountType::Expense],
            ['9000', 'Opening Balance',             AccountType::Equity],
        ];

        $map = [];
        foreach ($defs as [$code, $name, $type]) {
            $map[$code] = Account::create([
                'organization_id' => $this->org->id,
                'code' => $code,
                'name' => $name,
                'type' => $type->value,
                'is_active' => true,
            ]);
        }

        $this->bank = $map['1020'];
        $this->ar = $map['1100'];
        $this->vatInput = $map['1170'];
        $this->fixedAssetAcct = $map['1500'];
        $this->accumDepr = $map['1509'];
        $this->ap = $map['2000'];
        $this->vatOutput = $map['2200'];
        $this->vatPayable = $map['2201'];
        $this->avsPayable = $map['2270'];
        $this->acPayable = $map['2271'];
        $this->lppPayable = $map['2272'];
        $this->shareCapital = $map['2800'];
        $this->annualResult = $map['2900'];
        $this->revenue = $map['3000'];
        $this->rounding = $map['3900'];
        $this->salaries = $map['5000'];
        $this->socialCharges = $map['5700'];
        $this->generalExpenses = $map['6530'];
        $this->deprExpense = $map['6800'];
        $this->openingBalance = $map['9000'];
    }

    private function createInvoicingFixtures(): void
    {
        $this->vatNormal = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => '8.10',
            'code' => 'NORMAL',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Client AG',
            'email' => 'client@example.com',
        ]);
    }

    private function createPayrollFixtures(): void
    {
        $this->jean = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'ahv_number' => '756.1234.5678.01',
            'entry_date' => '2024-01-01',
            'gross_salary' => '6500.00',
            'is_active' => true,
            'is_source_tax_subject' => false,
        ]);

        $this->marie = Employee::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie.martin@example.com',
            'ahv_number' => '756.9876.5432.10',
            'entry_date' => '2024-01-01',
            'gross_salary' => '5200.00',
            'is_active' => true,
            'is_source_tax_subject' => false,
        ]);
    }

    private function createAssetFixtures(): void
    {
        $this->laptop = FixedAsset::create([
            'organization_id' => $this->org->id,
            'name' => 'Laptop MacBook Pro',
            'purchase_date' => '2024-12-31',
            'purchase_amount' => '2500.00',
            'useful_life_years' => 3,
            'salvage_value' => '0.00',
            'depreciation_method' => DepreciationMethod::Linear,
            'asset_account_id' => $this->fixedAssetAcct->id,
            'depreciation_expense_account_id' => $this->deprExpense->id,
            'accumulated_depreciation_account_id' => $this->accumDepr->id,
            'is_active' => true,
        ]);
    }

    private function createRecurringTemplate(): void
    {
        $this->recurringTemplate = RecurringInvoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'next_issue_date' => '2025-06-01',
            'is_active' => true,
            'template_data' => [
                'currency' => 'CHF',
                'notes' => null,
                'payment_terms' => null,
                'lines' => [
                    [
                        'description' => 'Service mensuel récurrent',
                        'quantity' => 1,
                        'unit_price' => '2000.00',
                        'vat_rate_id' => $this->vatNormal->id,
                        'sort_order' => 0,
                    ],
                ],
            ],
        ]);
    }
}
