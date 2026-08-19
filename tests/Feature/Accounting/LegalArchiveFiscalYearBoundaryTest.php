<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\LegalArchivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class LegalArchiveFiscalYearBoundaryTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_archive_uses_explicit_period_and_is_idempotent(): void
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
            ['2024-01-01', 'ARCHIVE-START'],
            ['2025-06-30', 'ARCHIVE-END'],
            ['2025-07-01', 'ARCHIVE-AFTER'],
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

        $service = app(LegalArchivingService::class);
        $service->archiveFiscalYear($this->organization->id, '2024', $fiscalYear->id);

        $archives = LegalArchive::query()
            ->where('document_type', 'journal_entry')
            ->get();

        $this->assertCount(2, $archives);
        $this->assertTrue($archives->every(fn (LegalArchive $archive): bool => $archive->fiscal_year_id === $fiscalYear->id));
        $this->assertFalse(Storage::disk('local')->exists('archives/'.$this->organization->id.'/2025/journal_entry/ARCHIVE-AFTER.json'));

        $service->archiveFiscalYear($this->organization->id, '2024', $fiscalYear->id);

        $this->assertSame(2, LegalArchive::query()->where('document_type', 'journal_entry')->count());
    }
}
