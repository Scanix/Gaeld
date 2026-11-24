<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalEntryAttachmentService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use TCPDF;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class JournalEntryAttachmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations, WithAuthenticatedOrganization;

    public function runDatabaseMigrations(): void
    {
        // Both upload workers need to see committed fixtures. Recreate the schema
        // afterwards since this test cannot use RefreshDatabase's transaction.
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('migrate:fresh')->assertSuccessful();
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_simultaneous_uploads_converge_to_one_attachment_and_one_quota_reservation(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test exercises concurrent PostgreSQL connections.');
        }

        $this->setUpOrganization();
        $organizationId = $this->organization->id;
        $userId = $this->user->id;
        $period = FiscalYear::factory()->for($this->organization)->operative()->create();
        $entryId = JournalEntry::create([
            'organization_id' => $organizationId,
            'reference' => 'UBS-CONCURRENT-ATTACHMENT',
            'date' => $period->start_date,
            'is_posted' => false,
        ])->id;
        $database = DB::getDatabaseName();
        $path = storage_path('framework/testing/concurrent-journal-upload-'.Str::uuid());
        File::makeDirectory($path, 0700, true);

        try {
            $pdf = new TCPDF;
            $pdf->AddPage();
            $pdf->Write(0, 'Justificatif concurrent');
            $bytes = $pdf->Output('', 'S');
            file_put_contents($path.'/original.pdf', $bytes);

            $tasks = [];

            foreach (['first', 'second'] as $worker) {
                $tasks[] = static function () use ($database, $path, $worker, $organizationId, $userId, $entryId): array {
                    if (! app()->environment('testing') || DB::getDatabaseName() !== $database) {
                        throw new RuntimeException('The worker must use the isolated testing database.');
                    }

                    config(['filesystems.disks.local.root' => $path.'/files']);
                    app(CurrentOrganization::class)->set(Organization::query()->findOrFail($organizationId));
                    app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);
                    $entry = JournalEntry::query()->findOrFail($entryId);
                    $user = User::query()->findOrFail($userId);

                    touch($path.'/ready-'.$worker);

                    $deadline = microtime(true) + 10;

                    while (count(glob($path.'/ready-*') ?: []) !== 2) {
                        if (microtime(true) >= $deadline) {
                            throw new RuntimeException('The second uploader did not reach the barrier.');
                        }

                        usleep(10000);
                    }

                    $result = app(JournalEntryAttachmentService::class)->add(
                        $entry,
                        new UploadedFile($path.'/original.pdf', $worker.'.pdf', 'application/pdf', test: true),
                        $user,
                    );

                    return ['id' => $result['attachment']->id, 'status' => $result['status']];
                };
            }

            [$first, $second] = Concurrency::driver('process')->run($tasks, timeout: 20);

            $this->assertSame($first['id'], $second['id']);
            $this->assertEqualsCanonicalizing(['added', 'duplicate'], [$first['status'], $second['status']]);
            $this->assertDatabaseCount('journal_entry_attachments', 1);
            $this->assertDatabaseCount('issued_document_files', 1);
            $this->assertSame(strlen($bytes), app(OrganizationDocumentStorageService::class)->bytesUsed($this->organization));
            $this->assertCount(1, File::allFiles($path.'/files'));
        } finally {
            File::deleteDirectory($path);
        }
    }
}
