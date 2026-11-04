<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Contracts\ArchivePackageBuilder;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use App\Jobs\ProcessArchivePackage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;
use ZipArchive;

class ArchivePackageTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->year = FiscalYear::factory()->for($this->organization)->create([
            'start_date' => '2026-07-08', 'end_date' => '2027-12-31', 'status' => FiscalYearStatus::Operative,
        ]);
        $bank = Account::create(['organization_id' => $this->organization->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset]);
        $income = Account::create(['organization_id' => $this->organization->id, 'code' => '3000', 'name' => 'Income', 'type' => AccountType::Revenue]);
        app(LedgerService::class)->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-07-08', reference: 'OPEN', description: 'Exact amount',
            lines: [new JournalLineData(accountId: (string) $bank->id, debit: '1234.56', credit: '0'), new JournalLineData(accountId: (string) $income->id, debit: '0', credit: '1234.56')],
        ));
    }

    public function test_complete_frozen_package_deduplicates_and_exports_original_bytes(): void
    {
        $this->assertInstanceOf(ArchivePackageService::class, app(ArchivePackageBuilder::class));
        $path = 'receipts/'.$this->organization->id.'/evidence.txt';
        Storage::disk('local')->put($path, 'Original supporting document');
        Expense::factory()->for($this->organization)->create(['date' => '2026-08-01', 'receipt_path' => $path]);
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year, $this->user);
        $same = $service->request($this->organization, $this->year, $this->user);
        $this->assertSame($package->id, $same->id);
        $this->assertSame('2037-12-31', $package->retention_until->toDateString());
        $this->assertCount(2, $package->snapshot['transaction_lines']);
        $this->assertSame('1234.56', $package->snapshot['transaction_lines'][0]['debit']);
        $this->assertDatabaseCount('archive_outbox', 1);
        app(OrganizationDocumentStorageService::class)->delete($this->organization, $path);
        Storage::disk('local')->assertExists($path);
        Account::where('code', '1020')->update(['name' => 'Changed live name']);
        $service->prepare($package);
        $this->assertSame('prepared', $package->fresh()->state);
        $this->assertTrue($service->verify($package)['valid']);
        $zipPath = $service->export($package);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('PDF', $manifest['report_profile']);
        $this->assertSame('native', $manifest['provenance']);
        $this->assertStringContainsString('Bank', $zip->getFromName('data/snapshot.json'));
        $source = $package->snapshot['original_sources'][0];
        $this->assertSame('Original supporting document', $zip->getFromName($source['package_path']));
        $this->assertStringStartsWith('%PDF', $zip->getFromName('reports/journal.pdf'));
        $zip->close();
        unlink($zipPath);
        $firstManifestHash = $package->fresh()->manifest_sha256;
        $service->prepare($package);
        $this->assertSame($firstManifestHash, $package->fresh()->manifest_sha256);
        $new = $service->request($this->organization, $this->year);
        $this->assertSame(2, $new->version);
        $this->assertTrue($service->verify($package)['valid']);
    }

    public function test_package_includes_versioned_instructions_policy_and_frozen_control_reports(): void
    {
        $audit = app(ArchiveAccessLog::class);
        $audit->record($this->organization->id, null, 'control.annual', $this->user, [
            'valid' => true, 'operator' => 'Appointed operator', 'restored_environment' => 'isolated-recovery',
            'package_hashes' => ['prior-package' => str_repeat('a', 64)],
        ]);
        $other = Organization::factory()->create();
        $audit->record($other->id, null, 'control.annual', metadata: ['private' => 'Other company control']);
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year, $this->user);
        $audit->record($this->organization->id, null, 'control.daily', metadata: ['after_capture' => true]);
        $service->prepare($package);
        $disk = Storage::disk('local');
        $manifest = json_decode($disk->get($package->package_path.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $files = collect($manifest['files'])->keyBy('path');
        $policy = json_decode($disk->get($package->package_path.'/retention-policy.json'), true, 512, JSON_THROW_ON_ERROR);
        $readme = $disk->get($package->package_path.'/README.txt');
        $this->assertSame('1.0.0', $policy['schema_version']);
        $this->assertSame($package->retention_until->toIso8601String(), $policy['minimum_retention_until']);
        $this->assertFalse($policy['automatic_disposal']);
        $this->assertSame('policy', $files['retention-policy.json']['role']);
        $this->assertSame(hash('sha256', $readme), $files['README.txt']['sha256']);
        $this->assertStringContainsString('expected manifest SHA-256', $readme);
        $this->assertStringContainsString('independently protected catalog', $readme);
        $this->assertSame(['control.annual'], array_column($package->snapshot['archive_access_events'], 'action'));
        $report = $package->snapshot['archive_access_events'][0]['metadata'];
        $this->assertSame('isolated-recovery', $report['restored_environment']);
        $this->assertSame(str_repeat('a', 64), $report['package_hashes']['prior-package']);
        $this->assertStringNotContainsString('Other company control', $disk->get($package->package_path.'/data/snapshot.json'));
        $hash = $package->manifest_sha256;
        $service->prepare($package);
        $this->assertSame($hash, $package->refresh()->manifest_sha256);
        $this->assertTrue($service->verify($package)['valid']);
    }

    public function test_missing_or_changed_original_fails_closed_without_regeneration(): void
    {
        $path = 'receipts/'.$this->organization->id.'/lost.pdf';
        Storage::disk('local')->put($path, 'original');
        Expense::factory()->for($this->organization)->create(['date' => '2026-09-01', 'receipt_path' => $path]);
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        Storage::disk('local')->put($path, 'replacement');
        try {
            $service->prepare($package);
            $this->fail('Changed originals must stop preparation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('changed since capture', $exception->getMessage());
        }
        $this->assertNull($package->fresh()->manifest_sha256);
        Storage::disk('local')->delete($path);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required original document is missing');
        $service->prepare($package);
    }

    public function test_retention_extension_does_not_change_partial_artifacts_during_resume(): void
    {
        $path = 'receipts/'.$this->organization->id.'/pending.pdf';
        Storage::disk('local')->put($path, 'original');
        Expense::factory()->for($this->organization)->create(['date' => '2026-09-01', 'receipt_path' => $path]);
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        $baseline = $package->retention_until->toIso8601String();
        Storage::disk('local')->put($path, 'changed');
        try {
            $service->prepare($package);
            $this->fail('Changed original must interrupt preparation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('changed since capture', $exception->getMessage());
        }
        $policy = Storage::disk('local')->get($package->package_path.'/retention-policy.json');
        $package->update(['retention_until' => $package->retention_until->addYears(3), 'legal_hold' => true]);
        Storage::disk('local')->put($path, 'original');
        $service->prepare($package);
        $this->assertSame($policy, Storage::disk('local')->get($package->package_path.'/retention-policy.json'));
        $manifest = json_decode(Storage::disk('local')->get($package->package_path.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($baseline, $manifest['retention_until']);
        $this->assertSame('2040-12-31', $package->fresh()->retention_until->toDateString());
        $this->assertTrue($package->fresh()->legal_hold);
    }

    public function test_tampered_package_cannot_be_exported_or_reprepared(): void
    {
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        $service->prepare($package);
        Storage::disk('local')->put($package->package_path.'/data/snapshot.json', '{}');
        $this->assertFalse($service->verify($package)['valid']);
        $this->expectException(\RuntimeException::class);
        $service->export($package);
    }

    public function test_request_and_outbox_are_atomic_and_cross_tenant_capture_is_rejected(): void
    {
        try {
            DB::transaction(function (): void {
                app(ArchivePackageService::class)->request($this->organization, $this->year);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('archive_packages', 0);
        $this->assertDatabaseCount('archive_outbox', 0);
        $this->expectException(\InvalidArgumentException::class);
        app(ArchivePackageService::class)->request(Organization::factory()->create(), $this->year);
    }

    public function test_outbox_dispatch_and_resume_are_durable_and_idempotent(): void
    {
        Bus::fake();
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        $this->artisan('archive:dispatch')->assertSuccessful();
        $this->artisan('archive:dispatch')->assertSuccessful();
        Bus::assertDispatchedTimes(ProcessArchivePackage::class, 1);
        (new ProcessArchivePackage($package->id))->handle($service);
        $this->assertNotNull(ArchiveOutbox::first()->completed_at);
        $service->retry($package);
        $this->assertNull(ArchiveOutbox::first()->completed_at);
        $this->assertSame('prepared', $package->fresh()->state);
    }

    public function test_exhausted_queue_dispatch_failures_become_visible_and_manual_retry_recovers(): void
    {
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        Bus::shouldReceive('dispatch')->times(3)->andThrow(new \RuntimeException('Queue unavailable'));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->artisan('archive:dispatch')->assertFailed();
            $this->travel(41)->minutes();
        }
        $this->assertNull(ArchiveOutbox::firstOrFail()->dispatched_at);
        $this->artisan('archive:dispatch')->assertSuccessful();
        $this->assertSame('failed', $package->fresh()->state);
        $this->assertDatabaseHas('archive_access_events', ['archive_package_id' => $package->id, 'action' => 'package.dispatch_exhausted']);
        $service->retry($package);
        $this->assertSame(0, ArchiveOutbox::firstOrFail()->attempts);
        Bus::fake();
        $this->artisan('archive:dispatch')->assertSuccessful();
        Bus::assertDispatchedTimes(ProcessArchivePackage::class, 1);
        (new ProcessArchivePackage($package->id))->handle($service);
        $this->assertSame('prepared', $package->fresh()->state);
        $this->assertNotNull(ArchiveOutbox::firstOrFail()->completed_at);
    }

    public function test_salary_pdf_versions_without_a_fiscal_year_link_are_captured_for_their_period(): void
    {
        $employee = Employee::create([
            'organization_id' => $this->organization->id, 'first_name' => 'Archive', 'last_name' => 'Employee',
            'entry_date' => '2026-07-01', 'gross_salary' => '5000.00',
        ]);
        $slip = SalarySlip::create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id,
            'period_month' => 9, 'period_year' => 2026, 'gross_salary' => '5000.00', 'net_salary' => '4500.00', 'deductions' => [],
        ]);
        foreach ([1, 2] as $version) {
            $bytes = '%PDF-1.7 salary original '.$version;
            $path = 'archives/issued/'.$this->organization->id.'/salary_slip_pdf/'.$slip->id.'/'.$version.'.pdf';
            Storage::disk('local')->put($path, $bytes);
            IssuedDocumentFile::create([
                'organization_id' => $this->organization->id, 'fiscal_year_id' => null,
                'document_type' => 'salary_slip_pdf', 'document_id' => $slip->id, 'version' => $version,
                'storage_path' => $path, 'checksum_sha256' => hash('sha256', $bytes), 'created_at' => now(),
            ]);
        }
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        $this->assertCount(2, $package->snapshot['issued_document_files']);
        $service->prepare($package);
        $this->assertTrue($service->verify($package)['valid']);
        foreach ($package->snapshot['original_sources'] as $source) {
            $this->assertStringStartsWith('%PDF-1.7 salary original ', Storage::disk('local')->get($package->package_path.'/'.$source['package_path']));
        }
    }

    public function test_database_guards_reject_raw_snapshot_replacement_and_audit_deletion(): void
    {
        $package = app(ArchivePackageService::class)->request($this->organization, $this->year);
        try {
            DB::transaction(fn () => DB::table('archive_packages')->where('id', $package->id)->update(['snapshot' => '{}']));
            $this->fail('Raw evidence replacement must be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('cannot be replaced', $exception->getMessage());
        }
        $event = app(ArchiveAccessLog::class)->record($this->organization->id, $package->id, 'control.annual', metadata: ['valid' => true]);
        try {
            DB::transaction(fn () => DB::table('archive_access_events')->where('id', $event->id)->delete());
            $this->fail('Raw audit deletion must be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
        $this->expectException(\LogicException::class);
        $package->update(['retention_until' => now()]);
    }

    public function test_historical_backfill_records_honest_provenance_and_resumes(): void
    {
        $this->artisan('archive:backfill', ['--organization' => $this->organization->id, '--fiscal-year' => $this->year->id])->assertSuccessful();
        $this->artisan('archive:backfill', ['--organization' => $this->organization->id, '--fiscal-year' => $this->year->id])->assertSuccessful();
        $this->assertDatabaseCount('archive_packages', 1);
        $package = ArchivePackage::firstOrFail();
        $this->assertSame('historical_backfill', $package->provenance);
        app(ArchivePackageService::class)->prepare($package);
        $manifest = json_decode(Storage::disk('local')->get($package->package_path.'/manifest.json'), true);
        $this->assertFalse($manifest['historical_integrity_asserted']);
    }

    public function test_supporting_documents_are_preserved_before_the_first_daily_capture(): void
    {
        $path = 'receipts/'.$this->organization->id.'/original.txt';
        Storage::disk('local')->put($path, 'First receipt');
        $expense = Expense::factory()->for($this->organization)->create(['date' => '2026-09-01', 'receipt_path' => $path]);
        $replacement = 'receipts/'.$this->organization->id.'/replacement.txt';
        Storage::disk('local')->put($replacement, 'Second receipt');
        $expense->update(['receipt_path' => $replacement]);
        app(OrganizationDocumentStorageService::class)->delete($this->organization, $path);
        Storage::disk('local')->assertMissing($path);
        $versions = IssuedDocumentFile::query()->where('document_type', 'supporting_expense')->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('First receipt', Storage::disk('local')->get($versions[0]->storage_path));
        $package = app(ArchivePackageService::class)->request($this->organization, $this->year);
        app(ArchivePackageService::class)->prepare($package);
        $sources = collect($package->snapshot['original_sources'])->where('type', 'issued_document');
        $this->assertCount(2, $sources);
        $this->assertTrue(app(ArchivePackageService::class)->verify($package)['valid']);
    }

    public function test_posted_entries_and_lines_are_immutable_even_through_raw_queries(): void
    {
        $entry = JournalEntry::where('reference', 'OPEN')->firstOrFail();
        $line = $entry->lines()->firstOrFail();
        foreach ([
            fn () => DB::table('journal_entries')->where('id', $entry->id)->update(['description' => 'tampered']),
            fn () => DB::table('journal_entries')->where('id', $entry->id)->delete(),
            fn () => DB::table('transaction_lines')->where('id', $line->id)->update(['debit' => '999']),
            fn () => DB::table('transaction_lines')->where('id', $line->id)->delete(),
            fn () => DB::table('transaction_lines')->insert(['journal_entry_id' => $entry->id, 'account_id' => $line->account_id, 'debit' => '1', 'credit' => '0']),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Posted accounting evidence cannot be replaced.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Posted journal', $exception->getMessage());
            }
        }
        $this->assertSame('1234.56', $line->fresh()->debit);
    }

    public function test_initial_posted_classification_appends_protected_audit_evidence(): void
    {
        $entry = JournalEntry::where('reference', 'OPEN')->firstOrFail();
        DB::table('journal_entries')->where('id', $entry->id)->update(['type' => 'year_end_closing']);
        $event = DB::table('archive_access_events')->where('action', 'ledger.classification_changed')->first();
        $this->assertNotNull($event);
        $metadata = json_decode($event->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($entry->id, $metadata['entry_id']);
        $this->assertNull($metadata['previous_type']);
        $this->assertSame('year_end_closing', $metadata['new_type']);
        $this->assertSame($this->organization->id, $event->organization_id);
    }

    public function test_archived_entry_classification_cannot_be_changed_even_from_null(): void
    {
        $entry = JournalEntry::where('reference', 'OPEN')->firstOrFail();
        $entry->update(['archived_at' => now()]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Posted journal entries require a correcting entry');
        DB::transaction(fn () => DB::table('journal_entries')->where('id', $entry->id)->update(['type' => 'year_end_closing']));
    }

    public function test_matching_metadata_changes_preserve_financial_fields_and_append_evidence(): void
    {
        $line = JournalEntry::where('reference', 'OPEN')->firstOrFail()->lines()->firstOrFail();
        DB::table('transaction_lines')->where('id', $line->id)->update(['lettrage_key' => 'A']);
        $this->assertSame('1234.56', $line->fresh()->debit);
        $event = DB::table('archive_access_events')->where('action', 'ledger.matching_changed')->first();
        $this->assertNotNull($event);
        $metadata = json_decode($event->metadata, true);
        $this->assertNull($metadata['previous_key']);
        $this->assertSame('A', $metadata['new_key']);
        $package = app(ArchivePackageService::class)->request($this->organization, $this->year);
        $this->assertSame('ledger.matching_changed', $package->snapshot['archive_access_events'][0]['action']);
    }

    public function test_intermediate_files_are_removed_before_the_prepared_state_is_committed(): void
    {
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $this->year);
        $temporary = $package->package_path.'/working/interrupted.pdf';
        Storage::disk('local')->put($temporary, 'Incomplete work from an interrupted attempt');
        $observed = false;
        Event::listen('eloquent.updating: '.ArchivePackage::class, function (ArchivePackage $updating) use ($package, $temporary, &$observed): void {
            if ($updating->id === $package->id && $updating->state === 'prepared') {
                $this->assertFalse(Storage::disk('local')->exists($temporary));
                $observed = true;
            }
        });
        $service->prepare($package);
        $this->assertTrue($observed);
        $this->assertTrue($service->verify($package)['valid']);
    }
}
