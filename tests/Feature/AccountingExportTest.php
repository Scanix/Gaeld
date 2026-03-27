<?php

namespace Tests\Feature;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Jobs\GenerateAccountingExportJob;
use App\Domains\Reporting\Mail\AccountingExportReadyMail;
use App\Domains\Reporting\Services\AccountingExportService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class AccountingExportTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $user;

    private Organization $org;

    private Account $bankAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Fiduciary Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        $this->bankAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->revenueAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP controller tests
    // ──────────────────────────────────────────────────────────────

    public function test_index_page_requires_authentication(): void
    {
        $response = $this->get(route('accounting.export'));

        $response->assertRedirect();
    }

    public function test_index_page_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->get(route('accounting.export'));

        $response->assertStatus(200);
    }

    public function test_generate_dispatches_job_and_redirects(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('accounting.export.generate'), [
                'fiscal_year' => '2025',
            ]);

        $response->assertRedirect(route('accounting.export'));

        Queue::assertPushed(GenerateAccountingExportJob::class, function (GenerateAccountingExportJob $job) {
            return $job->orgId === $this->org->id
                && $job->fiscalYear === '2025'
                && $job->userId === (string) $this->user->id;
        });
    }

    public function test_generate_validates_fiscal_year(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('accounting.export.generate'), [
                'fiscal_year' => 'not-a-year',
            ]);

        $response->assertSessionHasErrors('fiscal_year');
        Queue::assertNothingPushed();
    }

    public function test_generate_requires_authentication(): void
    {
        Queue::fake();

        $response = $this->post(route('accounting.export.generate'), [
            'fiscal_year' => '2025',
        ]);

        $response->assertRedirect();
        Queue::assertNothingPushed();
    }

    public function test_download_requires_valid_signature(): void
    {
        // No signature at all — must be authenticated so auth middleware passes,
        // then the signed middleware returns 403 for the unsigned URL.
        $response = $this->actingAs($this->user)->get(route('accounting.export.download', ['path' => 'something.zip']));
        $response->assertStatus(403);
    }

    public function test_download_returns_file_with_valid_signed_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/test-export.zip', 'fake-zip-content');

        $url = URL::temporarySignedRoute(
            'accounting.export.download',
            now()->addDay(),
            ['path' => 'test-export.zip'],
        );

        $response = $this->actingAs($this->user)->get($url);

        $response->assertStatus(200);
    }

    public function test_download_returns_404_for_missing_file(): void
    {
        Storage::fake('local');

        $url = URL::temporarySignedRoute(
            'accounting.export.download',
            now()->addDay(),
            ['path' => 'nonexistent.zip'],
        );

        $response = $this->actingAs($this->user)->get($url);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  AccountingExportService unit-level tests
    // ──────────────────────────────────────────────────────────────

    public function test_generate_export_creates_zip_with_expected_entries(): void
    {
        Storage::fake('local');

        // Post a journal entry so there is ledger data
        $ledger = app(LedgerService::class);
        $ledger->postEntry($this->org->id, new JournalEntryData(
            date: '2025-06-15',
            reference: 'TEST-001',
            description: 'Test Revenue',
            lines: [
                new JournalLineData(
                    accountId: (string) $this->bankAccount->id,
                    debit: '1000.00',
                    credit: '0',
                    description: 'Bank',
                ),
                new JournalLineData(
                    accountId: (string) $this->revenueAccount->id,
                    debit: '0',
                    credit: '1000.00',
                    description: 'Revenue',
                ),
            ],
        ));

        $service = app(AccountingExportService::class);
        $zipPath = $service->generateExport($this->org->id, '2025');

        $this->assertTrue(file_exists($zipPath));

        $zip = new \ZipArchive;
        $zip->open($zipPath);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $zip->close();

        $this->assertContains('chart-of-accounts.csv', $entries);
        $this->assertContains('journal-entries.csv', $entries);
        $this->assertContains('trial-balance.csv', $entries);
        $this->assertContains('profit-and-loss.pdf', $entries);
        $this->assertContains('balance-sheet.pdf', $entries);
        $this->assertContains('invoices.csv', $entries);
        $this->assertContains('expenses.csv', $entries);
        $this->assertContains('vat-reports/Q1.pdf', $entries);
        $this->assertContains('vat-reports/Q2.pdf', $entries);
        $this->assertContains('vat-reports/Q3.pdf', $entries);
        $this->assertContains('vat-reports/Q4.pdf', $entries);

        // Clean up
        @unlink($zipPath);
    }

    public function test_generate_export_chart_of_accounts_csv_has_correct_headers(): void
    {
        Storage::fake('local');

        $service = app(AccountingExportService::class);
        $zipPath = $service->generateExport($this->org->id, '2025');

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $csvContent = $zip->getFromName('chart-of-accounts.csv');
        $zip->close();

        // Strip BOM
        $csvContent = ltrim($csvContent, "\xEF\xBB\xBF");
        $firstLine = strtok($csvContent, "\n");

        $this->assertStringContainsString('Code', $firstLine);
        $this->assertStringContainsString('Name', $firstLine);
        $this->assertStringContainsString('Type', $firstLine);

        @unlink($zipPath);
    }

    // ──────────────────────────────────────────────────────────────
    //  GenerateAccountingExportJob test
    // ──────────────────────────────────────────────────────────────

    public function test_job_sends_email_with_download_link(): void
    {
        Storage::fake('local');
        Mail::fake();

        $service = $this->createMock(AccountingExportService::class);
        $service->method('generateExport')
            ->willReturn(Storage::disk('local')->path('exports/accounting-test.zip'));

        Storage::disk('local')->put('exports/accounting-test.zip', 'zip-data');

        $this->app->instance(AccountingExportService::class, $service);

        $job = new GenerateAccountingExportJob($this->org->id, '2025', (string) $this->user->id);
        $job->handle(app(AccountingExportService::class));

        Mail::assertSent(AccountingExportReadyMail::class, function (AccountingExportReadyMail $mail) {
            return $mail->hasTo($this->user->email)
                && $mail->fiscalYear === '2025';
        });
    }
}
