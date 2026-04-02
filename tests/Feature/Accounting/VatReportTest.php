<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\PostVatSettlementAction;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Services\VatReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class VatReportTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-20 12:00:00');

        $this->setUpOrganization();

        // Chart of accounts required for VAT settlement
        Account::create(['organization_id' => $this->organization->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '1170', 'name' => 'VAT Input (recoverable)', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '2201', 'name' => 'VAT Settlement', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '6530', 'name' => 'Expense', 'type' => AccountType::Expense->value]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPostedJournalEntry(string $date = '2026-01-15'): JournalEntry
    {
        return JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => $date,
            'reference' => 'TEST-'.uniqid(),
            'description' => 'Test entry',
            'is_posted' => true,
        ]);
    }

    private function createVatEntry(JournalEntry $journalEntry, VatEntryType $type, float $base, float $vat): VatEntry
    {
        return VatEntry::create([
            'journal_entry_id' => $journalEntry->id,
            'vat_rate_id' => $this->vatRate->id,
            'base_amount' => $base,
            'vat_amount' => $vat,
            'type' => $type->value,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  VatReportService unit-level tests
    // ──────────────────────────────────────────────────────────────

    public function test_vat_report_aggregates_output_vat_by_rate(): void
    {
        $je1 = $this->createPostedJournalEntry('2026-01-15');
        $je2 = $this->createPostedJournalEntry('2026-02-10');

        $this->createVatEntry($je1, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($je2, VatEntryType::Output, 2000.00, 162.00);

        /** @var VatReportService $service */
        $service = app(VatReportService::class);
        $report = $service->generate($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertCount(1, $report['output_vat_by_rate']); // one rate
        $this->assertEquals('243.00', $report['total_output_vat']);
        $this->assertEquals('3000.00', $report['total_revenue']);
    }

    public function test_vat_report_aggregates_input_vat(): void
    {
        $je = $this->createPostedJournalEntry('2026-01-20');
        $this->createVatEntry($je, VatEntryType::Input, 500.00, 40.50);

        $service = app(VatReportService::class);
        $report = $service->generate($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertEquals('40.50', $report['input_vat']);
    }

    public function test_net_vat_equals_output_minus_input(): void
    {
        $je1 = $this->createPostedJournalEntry('2026-01-15');
        $je2 = $this->createPostedJournalEntry('2026-01-20');

        $this->createVatEntry($je1, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($je2, VatEntryType::Input, 500.00, 40.50);

        $service = app(VatReportService::class);
        $report = $service->generate($this->organization->id, '2026-01-01', '2026-03-31');

        $expected = bcsub('81.00', '40.50', 2);
        $this->assertEquals($expected, $report['net_vat']);
        $this->assertEquals($expected, $report['vat_payable']);
    }

    public function test_vat_report_excludes_entries_outside_period(): void
    {
        $inPeriod = $this->createPostedJournalEntry('2026-01-15');
        $outOfPeriod = $this->createPostedJournalEntry('2025-12-31');

        $this->createVatEntry($inPeriod, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($outOfPeriod, VatEntryType::Output, 5000.00, 405.00);

        $service = app(VatReportService::class);
        $report = $service->generate($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertEquals('81.00', $report['total_output_vat']);
    }

    public function test_vat_report_excludes_unposted_entries(): void
    {
        $unpostedJe = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-01-15',
            'reference' => 'DRAFT-001',
            'description' => 'Draft entry',
            'is_posted' => false,
        ]);

        $this->createVatEntry($unpostedJe, VatEntryType::Output, 1000.00, 81.00);

        $service = app(VatReportService::class);
        $report = $service->generate($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertEquals('0.00', $report['total_output_vat']);
    }

    // ──────────────────────────────────────────────────────────────
    //  PostVatSettlementAction tests
    // ──────────────────────────────────────────────────────────────

    public function test_post_vat_settlement_creates_balanced_journal_entry(): void
    {
        $je1 = $this->createPostedJournalEntry('2026-01-15');
        $je2 = $this->createPostedJournalEntry('2026-01-20');

        $this->createVatEntry($je1, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($je2, VatEntryType::Input, 500.00, 40.50);

        /** @var PostVatSettlementAction $action */
        $action = app(PostVatSettlementAction::class);
        $journalEntry = $action->execute($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertTrue($journalEntry->is_posted);
        $this->assertTrue($journalEntry->isBalanced());
    }

    public function test_post_vat_settlement_debits_2200_credits_1170(): void
    {
        $je1 = $this->createPostedJournalEntry('2026-01-15');
        $je2 = $this->createPostedJournalEntry('2026-01-20');

        $this->createVatEntry($je1, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($je2, VatEntryType::Input, 500.00, 40.50);

        $action = app(PostVatSettlementAction::class);
        $journalEntry = $action->execute($this->organization->id, '2026-01-01', '2026-03-31');

        $lines = $journalEntry->lines->load('account');

        $debit2200 = $lines->first(fn ($l) => optional($l->account)->code === '2200');
        $credit1170 = $lines->first(fn ($l) => optional($l->account)->code === '1170');
        $credit2201 = $lines->first(fn ($l) => optional($l->account)->code === '2201');

        $this->assertNotNull($debit2200, '2200 line missing');
        $this->assertNotNull($credit1170, '1170 line missing');
        $this->assertNotNull($credit2201, '2201 line missing');

        $this->assertEquals('81.00', number_format((float) $debit2200->debit, 2, '.', ''));
        $this->assertEquals('0.00', number_format((float) $debit2200->credit, 2, '.', ''));

        $this->assertEquals('0.00', number_format((float) $credit1170->debit, 2, '.', ''));
        $this->assertEquals('40.50', number_format((float) $credit1170->credit, 2, '.', ''));
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP route tests
    // ──────────────────────────────────────────────────────────────

    public function test_vat_report_route_returns_inertia_response(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.vat', ['from_date' => '2026-01-01', 'to_date' => '2026-03-31']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Reports/VatReport'));
    }

    public function test_vat_report_export_pdf_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.vat.export', ['format' => 'pdf', 'from_date' => '2026-01-01', 'to_date' => '2026-03-31']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_vat_report_export_csv_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.vat.export', ['format' => 'csv', 'from_date' => '2026-01-01', 'to_date' => '2026-03-31']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_post_settlement_route_creates_journal_entry_and_redirects(): void
    {
        $je1 = $this->createPostedJournalEntry('2026-01-15');
        $je2 = $this->createPostedJournalEntry('2026-01-20');

        $this->createVatEntry($je1, VatEntryType::Output, 1000.00, 81.00);
        $this->createVatEntry($je2, VatEntryType::Input, 500.00, 40.50);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post(route('reports.vat.settlement'), [
                'from_date' => '2026-01-01',
                'to_date' => '2026-03-31',
            ]);

        $response->assertRedirect(route('reports.vat', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-03-31',
        ]));

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->organization->id,
            'reference' => 'VAT-SETTLEMENT-2026-01-01-2026-03-31',
            'is_posted' => true,
        ]);
    }

    public function test_post_settlement_route_validates_dates(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post(route('reports.vat.settlement'), [
                'from_date' => '',
                'to_date' => '',
            ]);

        $response->assertSessionHasErrors(['from_date', 'to_date']);
    }
}
