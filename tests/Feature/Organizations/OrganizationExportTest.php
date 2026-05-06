<?php

namespace Tests\Feature\Organizations;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Jobs\ExportOrganizationDataJob;
use App\Domains\Organizations\Mail\OrganizationExportReadyMail;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class OrganizationExportTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP controller tests
    // ──────────────────────────────────────────────────────────────

    public function test_export_requires_authentication(): void
    {
        $response = $this->post(route('settings.export'));

        $response->assertRedirect();
    }

    public function test_export_dispatches_job_and_redirects(): void
    {
        Queue::fake();

        $response = $this->actAsOrg()
            ->post(route('settings.export'));

        $response->assertRedirect(route('settings'));
        $response->assertSessionHas('success');

        Queue::assertPushed(ExportOrganizationDataJob::class, function (ExportOrganizationDataJob $job) {
            return $job->organizationId === $this->org->id
                && $job->userId === (string) $this->user->id;
        });
    }

    public function test_download_requires_valid_signature(): void
    {
        $response = $this->actAsOrg()
            ->get(route('settings.export.download', ['path' => 'something.zip']));

        $response->assertStatus(403);
    }

    public function test_download_returns_file_with_valid_signed_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/test-org-export.zip', 'fake-zip-content');

        $url = URL::temporarySignedRoute(
            'settings.export.download',
            now()->addHours(48),
            ['path' => 'test-org-export.zip'],
        );

        $response = $this->actAsOrg()->get($url);

        $response->assertStatus(200);
    }

    public function test_download_returns_404_for_missing_file(): void
    {
        Storage::fake('local');

        $url = URL::temporarySignedRoute(
            'settings.export.download',
            now()->addHours(48),
            ['path' => 'nonexistent.zip'],
        );

        $response = $this->actAsOrg()->get($url);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  OrganizationExportService tests
    // ──────────────────────────────────────────────────────────────

    public function test_generate_creates_zip_with_expected_entries(): void
    {
        Storage::fake('local');

        // Seed some data
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        Contact::factory()->create(['organization_id' => $this->org->id]);

        Invoice::factory()->create([
            'organization_id' => $this->org->id,
            'status' => InvoiceStatus::Draft,
            'type' => InvoiceType::Invoice,
        ]);

        Expense::factory()->create(['organization_id' => $this->org->id]);

        $service = app(OrganizationExportService::class);
        $zipPath = $service->generate($this->org->id);

        $this->assertTrue(file_exists($zipPath));

        $zip = new \ZipArchive;
        $zip->open($zipPath);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $zip->close();

        // JSON exports
        $this->assertContains('organization.json', $entries);
        $this->assertContains('accounts.json', $entries);
        $this->assertContains('customers.json', $entries);
        $this->assertContains('suppliers.json', $entries);
        $this->assertContains('invoices.json', $entries);
        $this->assertContains('invoice_lines.json', $entries);
        $this->assertContains('invoice_payments.json', $entries);
        $this->assertContains('expenses.json', $entries);
        $this->assertContains('bank_accounts.json', $entries);
        $this->assertContains('bank_transactions.json', $entries);
        $this->assertContains('journal_entries.json', $entries);
        $this->assertContains('journal_lines.json', $entries);
        $this->assertContains('vat_rates.json', $entries);
        $this->assertContains('budgets.json', $entries);
        $this->assertContains('recurring_invoices.json', $entries);
        $this->assertContains('bank_imports.json', $entries);

        // CSV exports
        $this->assertContains('accounts.csv', $entries);
        $this->assertContains('customers.csv', $entries);
        $this->assertContains('suppliers.csv', $entries);
        $this->assertContains('invoices.csv', $entries);
        $this->assertContains('invoice_lines.csv', $entries);
        $this->assertContains('invoice_payments.csv', $entries);
        $this->assertContains('expenses.csv', $entries);
        $this->assertContains('bank_accounts.csv', $entries);
        $this->assertContains('bank_transactions.csv', $entries);
        $this->assertContains('journal_entries.csv', $entries);
        $this->assertContains('journal_lines.csv', $entries);

        @unlink($zipPath);
    }

    public function test_organization_json_contains_org_metadata(): void
    {
        Storage::fake('local');

        $service = app(OrganizationExportService::class);
        $zipPath = $service->generate($this->org->id);

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $orgJson = json_decode($zip->getFromName('organization.json'), true);
        $zip->close();

        $this->assertEquals($this->org->id, $orgJson['id']);
        $this->assertEquals($this->org->name, $orgJson['name']);
        $this->assertArrayHasKey('exported_at', $orgJson);

        @unlink($zipPath);
    }

    public function test_csv_has_utf8_bom_and_headers(): void
    {
        Storage::fake('local');

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        $service = app(OrganizationExportService::class);
        $zipPath = $service->generate($this->org->id);

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $csvContent = $zip->getFromName('accounts.csv');
        $zip->close();

        // Check UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);

        // Check headers
        $csvContent = ltrim($csvContent, "\xEF\xBB\xBF");
        $firstLine = strtok($csvContent, "\n");
        $this->assertStringContainsString('Code', $firstLine);
        $this->assertStringContainsString('Name', $firstLine);
        $this->assertStringContainsString('Type', $firstLine);

        @unlink($zipPath);
    }

    public function test_export_does_not_include_other_org_data(): void
    {
        Storage::fake('local');

        $otherOrg = Organization::factory()->create();
        Contact::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Other Org Customer']);
        Contact::factory()->create(['organization_id' => $this->org->id, 'name' => 'My Customer']);

        $service = app(OrganizationExportService::class);
        $zipPath = $service->generate($this->org->id);

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $customersJson = json_decode($zip->getFromName('customers.json'), true);
        $zip->close();

        $this->assertCount(1, $customersJson);
        $this->assertEquals('My Customer', $customersJson[0]['name']);

        @unlink($zipPath);
    }

    // ──────────────────────────────────────────────────────────────
    //  ExportOrganizationDataJob test
    // ──────────────────────────────────────────────────────────────

    public function test_job_sends_email_with_download_link(): void
    {
        Storage::fake('local');
        Mail::fake();

        $service = $this->createMock(OrganizationExportService::class);
        $service->method('generate')
            ->willReturn(Storage::disk('local')->path('exports/org-export-test.zip'));

        Storage::disk('local')->put('exports/org-export-test.zip', 'zip-data');

        $this->app->instance(OrganizationExportService::class, $service);

        $job = new ExportOrganizationDataJob($this->org->id, (string) $this->user->id);
        $job->handle(app(OrganizationExportService::class));

        Mail::assertSent(OrganizationExportReadyMail::class, function (OrganizationExportReadyMail $mail) {
            return $mail->hasTo($this->user->email);
        });
    }
}
