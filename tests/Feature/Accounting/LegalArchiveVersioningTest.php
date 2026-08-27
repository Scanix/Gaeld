<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\LegalArchivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class LegalArchiveVersioningTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
    }

    public function test_rearchiving_a_reopened_period_preserves_json_and_pdf_versions(): void
    {
        Storage::fake('local');

        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => '2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => FiscalYearStatus::Operative,
        ]);
        $bank = $this->createAccount('1020', 'Bank', AccountType::Asset);
        $revenue = $this->createAccount('3000', 'Revenue', AccountType::Revenue);
        $expense = $this->createAccount('6500', 'Office expenses', AccountType::Expense);

        $ledger = app(LedgerService::class);
        $original = $ledger->postEntry($this->organization->id, new JournalEntryData(
            date: '2024-06-01',
            reference: 'ORIGINAL-2024',
            description: 'Original transaction',
            lines: [
                new JournalLineData(accountId: (string) $bank->id, debit: '100.00', credit: '0.00'),
                new JournalLineData(accountId: (string) $revenue->id, debit: '0.00', credit: '100.00'),
            ],
        ));

        $service = new LegalArchivingService(
            app(FiscalYearService::class),
            app(GenerateArchivePdfAction::class),
        );
        $service->archiveFiscalYear($this->organization->id, '2024', $fiscalYear->id);

        $firstJson = LegalArchive::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'journal_entry')
            ->where('document_id', $original->id)
            ->firstOrFail();
        $firstPnl = LegalArchive::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->firstOrFail();

        $ledger->postEntry($this->organization->id, new JournalEntryData(
            date: '2024-12-15',
            reference: 'CORRECTION-2024',
            description: 'Correction after reopening',
            lines: [
                new JournalLineData(accountId: (string) $expense->id, debit: '25.00', credit: '0.00'),
                new JournalLineData(accountId: (string) $bank->id, debit: '0.00', credit: '25.00'),
            ],
        ));

        $service->archiveFiscalYear(
            $this->organization->id,
            '2024',
            $fiscalYear->id,
            preservePreviousVersion: true,
        );

        $jsonVersions = LegalArchive::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'journal_entry')
            ->where('document_id', $original->id)
            ->orderBy('version')
            ->get();
        $pnlVersions = LegalArchive::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->orderBy('version')
            ->get();

        $this->assertSame([1, 2], $jsonVersions->pluck('version')->all());
        $this->assertNotSame($jsonVersions[0]->storage_path, $jsonVersions[1]->storage_path);
        $this->assertTrue(Storage::disk('local')->exists($jsonVersions[0]->storage_path));
        $this->assertTrue(Storage::disk('local')->exists($jsonVersions[1]->storage_path));
        $this->assertSame($firstJson->checksum_sha256, $jsonVersions[0]->checksum_sha256);
        $this->assertSame([1, 2], $pnlVersions->pluck('version')->all());
        $this->assertNotSame($firstPnl->checksum_sha256, $pnlVersions[1]->checksum_sha256);
        $this->assertNotSame($pnlVersions[0]->storage_path, $pnlVersions[1]->storage_path);
    }

    private function createAccount(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $name,
            'type' => $type->value,
            'is_active' => true,
        ]);
    }
}
