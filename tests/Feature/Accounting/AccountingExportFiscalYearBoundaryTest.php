<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Reporting\Jobs\GenerateAccountingExportJob;
use App\Domains\Reporting\Services\AccountingExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class AccountingExportFiscalYearBoundaryTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_generate_accepts_explicit_fiscal_year_id_and_queues_it(): void
    {
        Queue::fake();

        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        $this->actAsOrg()
            ->post(route('accounting.export.generate'), [
                'fiscal_year_id' => $fiscalYear->id,
            ])
            ->assertRedirect(route('accounting.export'));

        Queue::assertPushed(GenerateAccountingExportJob::class, function (GenerateAccountingExportJob $job) use ($fiscalYear): bool {
            return $job->orgId === $this->organization->id
                && $job->fiscalYearId === $fiscalYear->id;
        });
    }

    public function test_export_uses_the_explicit_period_boundaries(): void
    {
        Storage::fake('local');

        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);
        $bank = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $revenue = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $ledger = app(LedgerService::class);
        foreach ([
            ['2024-01-01', 'IN-RANGE-START'],
            ['2025-06-30', 'IN-RANGE-END'],
            ['2025-07-01', 'OUT-OF-RANGE'],
        ] as [$date, $reference]) {
            $ledger->postEntry($this->organization->id, new JournalEntryData(
                date: $date,
                reference: $reference,
                description: $reference,
                lines: [
                    new JournalLineData(accountId: (string) $bank->id, debit: '100.00', credit: '0'),
                    new JournalLineData(accountId: (string) $revenue->id, debit: '0', credit: '100.00'),
                ],
            ));
        }

        $zipPath = app(AccountingExportService::class)->generateExport(
            $this->organization->id,
            '2024',
            $fiscalYear->id,
        );

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $journal = $zip->getFromName('journal-entries.csv');
        $zip->close();

        $this->assertNotFalse($journal);
        $this->assertStringContainsString('IN-RANGE-START', $journal);
        $this->assertStringContainsString('IN-RANGE-END', $journal);
        $this->assertStringNotContainsString('OUT-OF-RANGE', $journal);

        @unlink($zipPath);
    }
}
