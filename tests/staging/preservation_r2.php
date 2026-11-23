<?php

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use App\Jobs\ProcessArchivePackage;
use App\Support\FeatureFlag;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** This opt-in fixture never migrates, clears or restores a database. */
function preservationEnsure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, int> */
function preservationRowHashes(string $table): array
{
    $hashes = [];
    foreach (DB::table($table)->get() as $row) {
        $data = (array) $row;
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $hashes[$hash] = ($hashes[$hash] ?? 0) + 1;
    }
    ksort($hashes);

    return $hashes;
}

$options = getopt('', ['root:', 'database:', 'credentials:', 'output:', 'run-id:', 'queued', 'ee-version:']);
$stage = 'arguments';
$report = ['schema' => 1, 'kind' => 'r2-isolated-staging-business-acceptance', 'status' => 'failed', 'companies' => [], 'checks' => []];
$output = null;
try {
    foreach (['root', 'database', 'credentials', 'output'] as $required) {
        preservationEnsure(is_string($options[$required] ?? null), 'Required argument: '.$required);
    }
    $root = realpath($options['root']);
    preservationEnsure(is_string($root) && is_file($root.'/bootstrap/app.php'), 'An existing application root is required.');
    preservationEnsure(in_array($options['database'], ['gaeld_ce_preservation', 'gaeld_ee_preservation', 'testing_r2_release'], true), 'Only the dedicated preservation QA databases are allowed.');
    preservationEnsure(! file_exists($options['output']), 'The output report must be a new file.');
    preservationEnsure(is_file($options['credentials']) && (fileperms($options['credentials']) & 0077) === 0, 'Credentials require a private file (mode 0600).');
    $credentials = json_decode(file_get_contents($options['credentials']), true, 16, JSON_THROW_ON_ERROR);
    foreach (['owner', 'viewer'] as $role) {
        preservationEnsure(is_string($credentials[$role]['email'] ?? null) && preg_match('/@[^@]+\.(test|invalid)$/D', $credentials[$role]['email']) === 1
            && is_string($credentials[$role]['password'] ?? null) && strlen($credentials[$role]['password']) >= 20, 'Use synthetic owner/viewer emails and passwords of at least 20 characters.');
    }
    $runId = $options['run-id'] ?? bin2hex(random_bytes(8));
    preservationEnsure(is_string($runId) && preg_match('/^[a-zA-Z0-9_-]{6,40}$/D', $runId) === 1, 'Use a unique alphanumeric run identifier.');
    $stage = 'bootstrap';
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    preservationEnsure(DB::getDriverName() === 'pgsql' && DB::connection()->getDatabaseName() === $options['database'], 'The active PostgreSQL database does not match the authorized isolated database.');
    preservationEnsure(Schema::hasTable('archive_packages') && Schema::hasTable('archive_outbox'), 'Apply R2 migrations to the isolated database before running this fixture.');
    preservationEnsure(! config('archiving.proofs_enabled'), 'This fixture exercises the R2 content profile; automatic R3 proofs must be disabled.');
    if (isset($options['ee-version'])) {
        preservationEnsure(is_string($options['ee-version']) && config('plugins.enabled') && FeatureFlag::isSaas(), 'EE acceptance requires the installed plugin and SaaS mode to be enabled.');
        preservationEnsure(Artisan::call('edition:verify', ['--ee-version' => $options['ee-version']]) === 0, 'The installed EE version does not match the requested compatible pair.');
        $report['edition'] = ['name' => 'EE', 'version' => $options['ee-version'], 'pair_verified' => true];
    } else {
        preservationEnsure(! FeatureFlag::isSaas(), 'EE acceptance must specify the exact --ee-version.');
        $report['edition'] = ['name' => 'CE', 'pair_verified' => null];
    }
    foreach (['owner', 'viewer'] as $role) {
        preservationEnsure(! User::where('email', $credentials[$role]['email'])->exists(), 'Use new synthetic credentials for every run; existing users are never changed.');
    }
    $output = $options['output'];
    $report += ['run_id' => $runId, 'database' => $options['database'], 'started_at' => now()->toIso8601String(), 'boundary_contract' => '1.0.0', 'profile' => 'gaeld-content-package/1.0.0', 'http_acceptance' => 'pending', 'processing_mode' => isset($options['queued']) ? 'queued-worker' : 'direct-job-handler'];
    $baseline = [];
    foreach (['organizations', 'users', 'organization_users', 'accounts', 'fiscal_years', 'journal_entries', 'transaction_lines', 'expenses', 'invoices', 'legal_archives', 'issued_document_files', 'archive_packages', 'archive_outbox', 'archive_access_events'] as $table) {
        $baseline[$table] = preservationRowHashes($table);
    }
    $baselineFiles = [];
    foreach (['receipts', 'archives'] as $directory) {
        foreach (Storage::disk('local')->allFiles($directory) as $path) {
            $baselineFiles[$path] = hash('sha256', Storage::disk('local')->get($path));
        }
    }
    $stage = 'synthetic-accounts';
    if (DB::table('roles')->count() === 0 && DB::table('permissions')->count() === 0) {
        app(RolesAndPermissionsSeeder::class)->run();
    }
    preservationEnsure(DB::table('roles')->where('name', 'owner')->exists() && DB::table('roles')->where('name', 'viewer')->exists(), 'Seed the normal role definitions before using a partially configured isolated database.');
    $users = [];
    foreach (['owner', 'viewer'] as $role) {
        $users[$role] = app(UserService::class)->create(new CreateUserData('QA '.$runId.' '.$role, $credentials[$role]['email'], $credentials[$role]['password']));
        $users[$role]->markEmailAsVerified();
        $users[$role]->update(['onboarding_completed_at' => now()]);
    }
    unset($credentials);
    $report['users'] = ['owner_id' => $users['owner']->id, 'viewer_id' => $users['viewer']->id];
    $packages = [];
    $years = [];
    $organizations = [];
    $documents = [];
    $stage = 'synthetic-accounting';
    foreach (['Holding', 'Property', 'Software'] as $index => $label) {
        $organization = app(OrganizationService::class)->create($users['owner'], new CreateOrganizationData(name: 'QA '.$runId.' '.$label.' AG', currency: 'CHF', locale: 'en'));
        $organizations[] = $organization;
        app(OrganizationSetupService::class)->seedChartOfAccounts($organization, 'swiss_sme');
        if ($index === 0) {
            app(OrganizationService::class)->addMember($organization, $users['viewer'], 'viewer');
        }
        $year = FiscalYear::create(['organization_id' => $organization->id, 'name' => 'QA initial 2026–2027', 'start_date' => '2026-07-08', 'end_date' => '2027-12-31', 'status' => FiscalYearStatus::Operative]);
        $years[] = $year;
        $bank = $organization->accounts()->where('code', '1020')->firstOrFail();
        $equity = $organization->accounts()->where('code', '2800')->firstOrFail();
        $amount = '100000.0'.($index + 1);
        $entry = app(LedgerService::class)->postEntry($organization->id, new JournalEntryData('2026-10-01', 'QA-'.$runId.'-'.$index, 'Synthetic QA opening capital', [
            new JournalLineData((string) $bank->id, $amount, '0.00'), new JournalLineData((string) $equity->id, '0.00', $amount),
        ]));
        $path = 'receipts/'.$organization->id.'/qa-'.$runId.'.txt';
        $bytes = 'Synthetic original '.$runId.' '.$label."\n";
        preservationEnsure(Storage::disk('local')->put($path, $bytes), 'Cannot store the synthetic original.');
        Expense::create(['organization_id' => $organization->id, 'user_id' => $users['owner']->id, 'category' => 'office', 'description' => 'Synthetic supporting evidence', 'amount' => '12.34', 'vat_amount' => '0.00', 'date' => '2026-10-01', 'status' => ExpenseStatus::Pending, 'currency' => 'CHF', 'receipt_path' => $path]);
        $documents[$organization->id] = ['path' => $path, 'bytes' => $bytes];
        preservationEnsure(Artisan::call('archive:backfill', ['--organization' => $organization->id, '--fiscal-year' => $year->id]) === 0, 'Scoped historical capture failed.');
        $package = ArchivePackage::withoutGlobalScopes()->where('organization_id', $organization->id)->firstOrFail();
        preservationEnsure(Artisan::call('archive:backfill', ['--organization' => $organization->id, '--fiscal-year' => $year->id]) === 0, 'Repeated historical capture failed.');
        preservationEnsure(ArchivePackage::withoutGlobalScopes()->where('organization_id', $organization->id)->count() === 1, 'Unchanged capture must reuse its package.');
        $packages[] = $package;
        $report['companies'][] = ['organization_id' => $organization->id, 'name' => $organization->name, 'fiscal_year_id' => $year->id, 'journal_entry_id' => $entry->id, 'package_id' => $package->id, 'expected_amount' => $amount, 'original_sha256' => hash('sha256', $bytes)];
    }
    $service = app(ArchivePackageService::class);
    $stage = 'interrupted-preparation';
    $first = $packages[0];
    $document = $documents[$first->organization_id];
    Storage::disk('local')->delete($document['path']);
    $job = new ProcessArchivePackage($first->id);
    try {
        $job->handle($service);
        throw new RuntimeException('Missing source was incorrectly accepted.');
    } catch (RuntimeException $exception) {
        preservationEnsure(str_contains($exception->getMessage(), 'original document is missing'), 'Missing-source failure must be explicit.');
        $job->failed($exception);
    }
    preservationEnsure($first->refresh()->state === 'failed' && $first->manifest_sha256 === null, 'Failed preparation must remain incomplete.');
    $partial = [];
    foreach (Storage::disk('local')->allFiles($first->package_path) as $path) {
        $partial[$path] = hash('sha256', Storage::disk('local')->get($path));
    }
    Storage::disk('local')->put($document['path'], $document['bytes']);
    preservationEnsure(Artisan::call('archive:package', ['operation' => 'resume', 'package' => $first->id]) === 0, 'Durable resume failed.');
    if (isset($options['queued'])) {
        $packageIds = array_map(fn ($package) => $package->id, $packages);
        preservationEnsure(config('queue.default') !== 'sync', 'Queued acceptance requires a real worker connection.');
        preservationEnsure(! ArchiveOutbox::query()->whereNull('completed_at')->whereNotIn('archive_package_id', $packageIds)->exists(), 'Queued acceptance refuses to dispatch unrelated pending packages.');
        preservationEnsure(Artisan::call('archive:dispatch', ['--limit' => 3]) === 0, 'Outbox dispatch failed.');
        $deadline = microtime(true) + 60;
        while (ArchiveOutbox::query()->whereIn('archive_package_id', $packageIds)->whereNotNull('completed_at')->count() !== 3 && microtime(true) < $deadline) {
            usleep(200000);
        }
        preservationEnsure(ArchiveOutbox::query()->whereIn('archive_package_id', $packageIds)->whereNotNull('completed_at')->count() === 3, 'The real archive worker did not complete the three packages within 60 seconds.');
    }
    $stage = 'preparation-and-export';
    foreach ($packages as $index => $package) {
        if (! isset($options['queued'])) {
            (new ProcessArchivePackage($package->id))->handle($service);
        }
        $package->refresh();
        preservationEnsure($service->verify($package)['valid'], 'Package integrity verification failed.');
        preservationEnsure($package->snapshot['organization'][0]['id'] === $package->organization_id, 'Snapshot company differs from package company.');
        preservationEnsure(count($package->snapshot['journal_entries']) === 1 && count($package->snapshot['transaction_lines']) === 2, 'Accounting records leaked between the three companies.');
        $debits = array_column($package->snapshot['transaction_lines'], 'debit');
        preservationEnsure(in_array($report['companies'][$index]['expected_amount'], $debits, true), 'Exact decimal amount was not preserved.');
        $zipPath = $service->export($package);
        $zip = new ZipArchive;
        preservationEnsure($zip->open($zipPath) === true, 'Export ZIP cannot be opened.');
        $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        preservationEnsure(hash('sha256', $zip->getFromName('manifest.json')) === $package->manifest_sha256, 'Exported manifest differs from retained bytes.');
        preservationEnsure($manifest['proof_profile'] === 'gaeld-content-package/1.0.0' && $manifest['historical_integrity_asserted'] === false, 'The content profile must not claim sealed or retrospective proof.');
        foreach ($package->snapshot['original_sources'] as $source) {
            preservationEnsure(hash('sha256', $zip->getFromName($source['package_path'])) === $source['sha256'], 'An original changed in export.');
        }
        $zip->close();
        unlink($zipPath);
        $report['companies'][$index] += ['manifest_sha256' => $package->manifest_sha256, 'snapshot_sha256' => $package->snapshot_sha256, 'state' => $package->state, 'provenance' => $package->provenance, 'export_route' => '/accounting/conservation/'.$package->id.'/export'];
        preservationEnsure(Artisan::call('archive:check', ['--organization' => $package->organization_id]) === 0, 'Scoped local control failed.');
    }
    foreach ($partial as $path => $hash) {
        preservationEnsure(hash('sha256', Storage::disk('local')->get($path)) === $hash, 'Resume replaced a previously written artifact.');
    }
    preservationEnsure(ArchiveOutbox::query()->whereIn('archive_package_id', array_map(fn ($package) => $package->id, $packages))->whereNotNull('completed_at')->count() === 3, 'The durable outbox was not completed.');
    $stage = 'invariants';
    try {
        $service->request($organizations[0], $years[1]);
        throw new RuntimeException('Cross-company capture was incorrectly accepted.');
    } catch (InvalidArgumentException) {
        $report['checks']['cross_company_capture_rejected'] = true;
    }
    try {
        DB::transaction(fn () => DB::table('transaction_lines')->where('journal_entry_id', $report['companies'][0]['journal_entry_id'])->update(['debit' => '99999.99']));
        throw new RuntimeException('Posted accounting values were incorrectly mutable.');
    } catch (QueryException) {
        $report['checks']['posted_financial_values_protected'] = true;
    }
    preservationEnsure(Artisan::call('archive:check', ['--organization' => $organizations[0]->id, '--annual' => true]) !== 0, 'Annual control must require an actual independent recovery report.');
    $report['checks'] += ['three_company_snapshot_isolation' => true, 'exact_decimals' => true, 'original_bytes_and_export' => true, 'historical_capture_deduplicated' => true, 'missing_original_failed_closed' => true, 'partial_preparation_resumed' => true, 'durable_outbox_completed' => true, 'annual_recovery_evidence_required' => true];
    $stage = 'baseline-preservation';
    foreach ($baseline as $table => $before) {
        $after = preservationRowHashes($table);
        foreach ($before as $hash => $count) {
            preservationEnsure(($after[$hash] ?? 0) >= $count, 'A pre-existing row changed in '.$table.'.');
        }
        $report['baseline'][$table] = ['existing_rows' => array_sum($before), 'unchanged' => true, 'row_hash_inventory_sha256' => hash('sha256', json_encode($before, JSON_THROW_ON_ERROR))];
    }
    foreach ($baselineFiles as $path => $hash) {
        preservationEnsure(Storage::disk('local')->exists($path) && hash('sha256', Storage::disk('local')->get($path)) === $hash, 'A pre-existing evidence file changed.');
    }
    $report['checks']['pre_existing_rows_and_files_unchanged'] = true;
    $report['baseline_file_count'] = count($baselineFiles);
    $report['status'] = 'passed';
    $report['completed_at'] = now()->toIso8601String();
} catch (Throwable $exception) {
    $report['failed_stage'] = $stage;
    $report['error_class'] = $exception::class;
    if ($exception::class === RuntimeException::class) {
        $report['error'] = $exception->getMessage();
    }
}
if ($output !== null) {
    $stream = fopen($output, 'xb');
    if ($stream === false) {
        fwrite(STDERR, "Cannot create the new QA report.\n");
        exit(1);
    }
    chmod($output, 0600);
    fwrite($stream, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    fclose($stream);
}
echo json_encode(['status' => $report['status'], 'stage' => $report['failed_stage'] ?? 'complete', 'report_written' => $output !== null], JSON_THROW_ON_ERROR)."\n";
exit($report['status'] === 'passed' ? 0 : 1);
