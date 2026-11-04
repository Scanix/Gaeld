<?php

// Synthetic fixture used only by the opt-in, isolated real adoption rehearsal.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Jobs\ExportOrganizationDataJob;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\Services\UserService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

$operation = $argv[1] ?? 'snapshot';
if ($operation === 'seed') {
    if (Organization::exists()) {
        throw new RuntimeException('The rehearsal may only seed its empty isolated database.');
    }
    $credentials = json_decode(file_get_contents('/tmp/rehearsal-owner.json'), true, flags: JSON_THROW_ON_ERROR);
    $owner = app(UserService::class)->create(new CreateUserData('Rehearsal Owner', $credentials['email'], $credentials['password']));
    $owner->markEmailAsVerified();
    foreach (['Rehearsal Holding AG', 'Rehearsal Property AG', 'Rehearsal Software AG'] as $index => $name) {
        $organization = app(OrganizationService::class)->create($owner, new CreateOrganizationData(name: $name, currency: 'CHF', locale: 'en'));
        app(OrganizationSetupService::class)->seedChartOfAccounts($organization, 'swiss_sme');
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $cash = $organization->accounts()->where('code', '1020')->firstOrFail();
        $equity = $organization->accounts()->where('code', '2800')->firstOrFail();
        $journal = app(LedgerService::class)->postEntry($organization->id, new JournalEntryData('2026-10-01', 'REHEARSAL-'.$index, 'Synthetic opening capital', [
            new JournalLineData($cash->id, '100000.00', '0.00'), new JournalLineData($equity->id, '0.00', '100000.00'),
        ]));
        $path = 'receipts/'.$organization->id.'/rehearsal.txt';
        Storage::disk('local')->put($path, 'Synthetic supporting document '.$index);
        ReceiptScan::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'scan_id' => 'rehearsal-'.$index,
            'receipt_path' => $path, 'status' => 'completed', 'expires_at' => now()->addYears(2)]);
        $archivePath = 'archives/'.$organization->id.'/historical-journal.json';
        $archiveBytes = json_encode(['reference' => $journal->reference, 'description' => 'Synthetic historical original', 'debit' => '100000.00'], JSON_THROW_ON_ERROR);
        Storage::disk('local')->put($archivePath, $archiveBytes);
        LegalArchive::create(['organization_id' => $organization->id, 'document_type' => 'journal_entry', 'document_id' => $journal->id,
            'version' => 2, 'fiscal_year' => 2026, 'fiscal_year_id' => $fiscalYear->id, 'checksum_sha256' => hash('sha256', $archiveBytes),
            'storage_path' => $archivePath, 'archived_at' => '2026-10-02 10:00:00', 'expires_at' => '2036-12-31 23:59:59']);
    }
    Storage::disk('local')->put('rehearsal/encrypted.txt', Crypt::encryptString('Rehearsal encrypted continuity proof'));
    Redis::connection('default')->set('rehearsal:marker', 'queue-and-key-preserved');
    ExportOrganizationDataJob::dispatch(Organization::orderBy('id')->first()->id, (string) $owner->id)->delay(now()->addDays(2));
} elseif ($operation === 'mutate') {
    DB::table('organizations')->update(['name' => 'Controlled restore mutation']);
    foreach (array_merge(Storage::disk('local')->allFiles('receipts'), Storage::disk('local')->allFiles('archives')) as $path) {
        Storage::disk('local')->delete($path);
    }
    Redis::connection('default')->set('rehearsal:marker', 'controlled-mutation');
}
$result = ['tables' => [], 'documents' => [], 'encrypted_value' => null, 'redis' => []];
foreach (['organizations', 'users', 'organization_users', 'accounts', 'fiscal_years', 'journal_entries', 'transaction_lines', 'receipt_scans', 'legal_archives'] as $table) {
    $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
    $result['tables'][$table] = ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows))];
}
foreach (array_merge(Storage::disk('local')->allFiles('receipts'), Storage::disk('local')->allFiles('archives')) as $path) {
    $result['documents'][$path] = hash('sha256', Storage::disk('local')->get($path));
}
ksort($result['documents']);
$result['encrypted_value'] = hash('sha256', Crypt::decryptString(Storage::disk('local')->get('rehearsal/encrypted.txt')));
$result['redis']['marker'] = Redis::connection('default')->get('rehearsal:marker');
$result['redis']['delayed'] = Redis::connection('default')->zrange('queues:default:delayed', 0, -1);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
