<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveMigrationUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    private const PRESERVATION_MIGRATION = '2026_11_04_193239_create_archive_packages_and_outbox_tables.php';

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertMatchesRegularExpression('/(?:^|_)test(?:ing)?(?:_|$)/', DB::connection()->getDatabaseName());
    }

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        $existing = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path): bool => basename($path) !== self::PRESERVATION_MIGRATION));

        return ['--realpath' => true, '--path' => $existing, '--force' => true];
    }

    public function test_populated_legacy_schema_upgrades_without_changing_existing_evidence_or_key(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(Schema::hasTable('archive_packages'));
        $this->assertFalse(Schema::hasTable('issued_document_files'));
        Storage::fake('local');
        $organization = Organization::factory()->create(['id' => '11111111-1111-4111-8111-111111111111']);
        $year = FiscalYear::factory()->for($organization)->create([
            'id' => '22222222-2222-4222-8222-222222222222', 'start_date' => '2026-07-08', 'end_date' => '2027-12-31',
        ]);
        $bank = Account::create(['organization_id' => $organization->id, 'code' => '1020', 'name' => 'Legacy bank', 'type' => AccountType::Asset]);
        $cost = Account::create(['organization_id' => $organization->id, 'code' => '6500', 'name' => 'Legacy cost', 'type' => AccountType::Expense]);
        $entryId = '33333333-3333-4333-8333-333333333333';
        $expenseId = '44444444-4444-4444-8444-444444444444';
        $archiveId = '55555555-5555-4555-8555-555555555555';
        $recordedAt = '2026-08-15 10:00:00';
        $receiptPath = 'receipts/'.$organization->id.'/legacy-original.pdf';
        $archivePath = 'archives/'.$organization->id.'/legacy-expense-v2.json';
        $receiptBytes = "%PDF-1.4\nOriginal supplier document predating the preservation release\n";
        $archiveBytes = '{"legacy_version":2,"amount":"1250.45","document_id":"'.$expenseId.'"}';
        Storage::disk('local')->put($receiptPath, $receiptBytes);
        Storage::disk('local')->put($archivePath, $archiveBytes);

        DB::table('journal_entries')->insert([
            'id' => $entryId, 'organization_id' => $organization->id, 'date' => '2026-08-15',
            'reference' => 'LEGACY-2026-001', 'description' => 'Previously posted supplier cost', 'is_posted' => true,
            'created_at' => $recordedAt, 'updated_at' => $recordedAt,
        ]);
        foreach ([[$cost->id, '1250.45', '0.00'], [$bank->id, '0.00', '1250.45']] as [$accountId, $debit, $credit]) {
            DB::table('transaction_lines')->insert([
                'journal_entry_id' => $entryId, 'account_id' => $accountId, 'debit' => $debit, 'credit' => $credit,
                'created_at' => $recordedAt, 'updated_at' => $recordedAt,
            ]);
        }
        DB::table('expenses')->insert([
            'id' => $expenseId, 'organization_id' => $organization->id, 'journal_entry_id' => $entryId,
            'category' => 'office', 'description' => 'Legacy supplier invoice', 'amount' => '1250.45', 'vat_amount' => '0.00',
            'date' => '2026-08-15', 'status' => 'posted', 'currency' => 'CHF', 'receipt_path' => $receiptPath,
            'archived_at' => '2026-09-01 12:00:00', 'created_at' => $recordedAt, 'updated_at' => $recordedAt,
        ]);
        DB::table('legal_archives')->insert([
            'id' => $archiveId, 'organization_id' => $organization->id, 'document_type' => 'expense', 'document_id' => $expenseId,
            'version' => 2, 'fiscal_year' => 2026, 'fiscal_year_id' => $year->id,
            'storage_path' => $archivePath, 'checksum_sha256' => hash('sha256', $archiveBytes),
            'archived_at' => '2026-09-01 12:00:00', 'expires_at' => '2037-12-31 23:59:59',
            'created_at' => $recordedAt, 'updated_at' => $recordedAt,
        ]);
        $before = [];
        foreach (['organizations', 'fiscal_years', 'accounts', 'journal_entries', 'transaction_lines', 'expenses', 'legal_archives'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $applicationKey = config('app.key');
        $encrypted = Crypt::encryptString('Legacy encrypted data must remain readable');

        $this->artisan('migrate', [
            '--realpath' => true, '--path' => database_path('migrations/'.self::PRESERVATION_MIGRATION), '--force' => true,
        ])->assertSuccessful();

        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table.' evidence changed during upgrade');
        }
        $this->assertSame($applicationKey, config('app.key'));
        $this->assertSame('Legacy encrypted data must remain readable', Crypt::decryptString($encrypted));
        $this->assertSame(hash('sha256', $receiptBytes), hash('sha256', Storage::disk('local')->get($receiptPath)));
        $this->assertSame(hash('sha256', $archiveBytes), hash('sha256', Storage::disk('local')->get($archivePath)));
        $this->assertTrue(Schema::hasTable('archive_packages'));
        $this->assertTrue(Schema::hasTable('issued_document_files'));

        foreach ([
            fn () => DB::table('transaction_lines')->where('journal_entry_id', $entryId)->update(['debit' => '9999.00']),
            fn () => DB::table('expenses')->where('id', $expenseId)->update(['amount' => '9999.00']),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('New preservation guards must protect existing evidence.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('correcting', $exception->getMessage());
            }
        }

        $packages = app(ArchivePackageService::class);
        $package = $packages->request($organization, $year, provenance: 'historical_backfill');
        $packages->prepare($package);
        $this->assertTrue($packages->verify($package)['valid']);
        $this->assertSame($entryId, $package->snapshot['journal_entries'][0]['id']);
        $this->assertSame($archiveId, $package->snapshot['legal_archives'][0]['id']);
        $this->assertSame('2', $package->snapshot['legal_archives'][0]['version']);
        $this->assertCount(2, $package->snapshot['original_sources']);
        foreach ($package->snapshot['original_sources'] as $source) {
            $expected = $source['type'] === 'receipt' ? $receiptBytes : $archiveBytes;
            $this->assertSame($expected, Storage::disk('local')->get($package->package_path.'/'.$source['package_path']));
        }
        $this->assertDatabaseCount('archive_outbox', 1);
    }
}
