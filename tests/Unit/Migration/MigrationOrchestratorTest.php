<?php

namespace Tests\Unit\Migration;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ParseResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Mappers\FuzzyNameAccountMapper;
use App\Domains\Migration\Mappers\NumberPatternAccountMapper;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Migration\Services\MigrationRegistry;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MigrationOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private MigrationRegistry $registry;

    private MigrationOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Migration Test Org',
            'currency' => 'CHF',
        ]);

        $this->registry = new MigrationRegistry;
        $this->orchestrator = new MigrationOrchestrator($this->registry);
    }

    public function test_start_session_creates_pending_session(): void
    {
        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $this->assertInstanceOf(MigrationSession::class, $session);
        $this->assertSame(Platform::Bexio, $session->platform);
        $this->assertSame(ImportStatus::Pending, $session->status);
        $this->assertSame($this->user->id, $session->created_by);
        $this->assertSame($this->organization->id, $session->organization_id);
        $this->assertDatabaseHas('migration_sessions', ['id' => $session->id]);
    }

    public function test_parse_file_returns_error_for_unsupported_platform(): void
    {
        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        // No parsers registered
        $file = UploadedFile::fake()->create('accounts.csv', 100, 'text/csv');
        $result = $this->orchestrator->parseFile($session, $file, DataType::Accounts);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('Unsupported platform', $result->errors[0]);
    }

    public function test_parse_file_returns_error_for_unsupported_data_type(): void
    {
        $parser = $this->createMock(PlatformParserInterface::class);
        $parser->method('platform')->willReturn(Platform::Bexio);
        $parser->method('supportedDataTypes')->willReturn([DataType::Accounts]);

        $this->registry->registerParser($parser);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $file = UploadedFile::fake()->create('contacts.csv', 100, 'text/csv');
        $result = $this->orchestrator->parseFile($session, $file, DataType::Contacts);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('does not support', $result->errors[0]);
    }

    public function test_parse_file_delegates_to_parser(): void
    {
        $rows = collect([
            new AccountImportRow(1, '1020', 'Bank', 'asset'),
            new AccountImportRow(2, '3000', 'Revenue', 'revenue'),
        ]);

        $parser = $this->createMock(PlatformParserInterface::class);
        $parser->method('platform')->willReturn(Platform::GenericCsv);
        $parser->method('supportedDataTypes')->willReturn(DataType::cases());
        $parser->method('parse')->willReturn($rows);

        $this->registry->registerParser($parser);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::GenericCsv,
            $this->user->id,
        );

        $file = UploadedFile::fake()->create('accounts.csv', 100, 'text/csv');
        $result = $this->orchestrator->parseFile($session, $file, DataType::Accounts);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $result->totalCount());
    }

    public function test_preview_returns_correct_counts(): void
    {
        $row1 = new AccountImportRow(1, '1020', 'Bank', 'asset');
        $row2 = new AccountImportRow(2, '3000', 'Revenue', 'revenue');
        $row3 = new AccountImportRow(3, '', 'No Code', 'asset');
        $row3->markInvalid();

        $rows = collect([$row1, $row2, $row3]);

        $importer = $this->createMock(DataTypeImporterInterface::class);
        $importer->method('dataType')->willReturn(DataType::Accounts);
        $importer->method('validate')->willReturn(ValidationResult::success());
        $this->registry->registerImporter($importer);

        $preview = $this->orchestrator->preview($rows, DataType::Accounts, $this->organization);

        $this->assertSame(3, $preview->totalRows);
        $this->assertSame(2, $preview->validRows);
        $this->assertSame(1, $preview->invalidRows);
        $this->assertCount(3, $preview->sampleRows);
    }

    public function test_preview_includes_account_mappings_for_journal_entries(): void
    {
        // Create target accounts
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $row = new AccountImportRow(1, '1020', 'Bank Account', 'asset');
        $rows = collect([$row]);

        $importer = $this->createMock(DataTypeImporterInterface::class);
        $importer->method('dataType')->willReturn(DataType::OpeningBalances);
        $importer->method('validate')->willReturn(ValidationResult::success());
        $this->registry->registerImporter($importer);

        $mapper = new NumberPatternAccountMapper;
        $this->registry->registerMapper($mapper);

        $preview = $this->orchestrator->preview($rows, DataType::OpeningBalances, $this->organization);

        $this->assertNotEmpty($preview->accountMappings);
        $this->assertArrayHasKey('1020', $preview->accountMappings);
        $this->assertSame(1.0, $preview->accountMappings['1020']['confidence']);
    }

    public function test_execute_import_returns_failure_when_no_importer(): void
    {
        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $rows = collect([new AccountImportRow(1, '1020', 'Bank', 'asset')]);
        $result = $this->orchestrator->executeImport($session, $rows, DataType::Accounts, $this->organization);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No importer', $result->errors[0]);
    }

    public function test_execute_import_updates_session_status_on_validation_failure(): void
    {
        $importer = $this->createMock(DataTypeImporterInterface::class);
        $importer->method('dataType')->willReturn(DataType::Accounts);
        $importer->method('validate')->willReturn(ValidationResult::failure(['Missing required fields']));
        $this->registry->registerImporter($importer);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $rows = collect([new AccountImportRow(1, '1020', 'Bank', 'asset')]);
        $result = $this->orchestrator->executeImport($session, $rows, DataType::Accounts, $this->organization);

        $this->assertFalse($result->success);
        $session->refresh();
        $this->assertSame(ImportStatus::Failed->value, $session->data_types_status['accounts']);
    }

    public function test_execute_import_succeeds_and_updates_session(): void
    {
        $importer = $this->createMock(DataTypeImporterInterface::class);
        $importer->method('dataType')->willReturn(DataType::Contacts);
        $importer->method('validate')->willReturn(ValidationResult::success());
        $importer->method('import')->willReturn(ImportResult::success(DataType::Contacts, 5, 1));
        $this->registry->registerImporter($importer);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $rows = collect([new ContactImportRow(1, 'customer', 'Test Co')]);
        $result = $this->orchestrator->executeImport($session, $rows, DataType::Contacts, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->importedCount);

        $session->refresh();
        $this->assertSame(ImportStatus::Completed->value, $session->data_types_status['contacts']);
        $this->assertSame(5, $session->imported_counts['contacts']);
    }

    public function test_execute_all_processes_in_dependency_order_and_completes(): void
    {
        $accountImporter = $this->createMock(DataTypeImporterInterface::class);
        $accountImporter->method('dataType')->willReturn(DataType::Accounts);
        $accountImporter->method('dependencies')->willReturn([]);
        $accountImporter->method('validate')->willReturn(ValidationResult::success());
        $accountImporter->method('import')->willReturn(ImportResult::success(DataType::Accounts, 10));

        $contactImporter = $this->createMock(DataTypeImporterInterface::class);
        $contactImporter->method('dataType')->willReturn(DataType::Contacts);
        $contactImporter->method('dependencies')->willReturn([]);
        $contactImporter->method('validate')->willReturn(ValidationResult::success());
        $contactImporter->method('import')->willReturn(ImportResult::success(DataType::Contacts, 5));

        $this->registry->registerImporter($accountImporter);
        $this->registry->registerImporter($contactImporter);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $rowsByType = [
            'accounts' => collect([new AccountImportRow(1, '1020', 'Bank', 'asset')]),
            'contacts' => collect([new ContactImportRow(1, 'customer', 'Test')]),
        ];

        $results = $this->orchestrator->executeAll($session, $rowsByType, $this->organization);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->success);
        $this->assertTrue($results[1]->success);

        $session->refresh();
        $this->assertSame(ImportStatus::Completed, $session->status);
        $this->assertNotNull($session->completed_at);
    }

    public function test_execute_all_marks_partially_completed_on_mixed_results(): void
    {
        $accountImporter = $this->createMock(DataTypeImporterInterface::class);
        $accountImporter->method('dataType')->willReturn(DataType::Accounts);
        $accountImporter->method('dependencies')->willReturn([]);
        $accountImporter->method('validate')->willReturn(ValidationResult::success());
        $accountImporter->method('import')->willReturn(ImportResult::success(DataType::Accounts, 10));

        $contactImporter = $this->createMock(DataTypeImporterInterface::class);
        $contactImporter->method('dataType')->willReturn(DataType::Contacts);
        $contactImporter->method('dependencies')->willReturn([]);
        $contactImporter->method('validate')->willReturn(ValidationResult::failure(['Invalid data']));

        $this->registry->registerImporter($accountImporter);
        $this->registry->registerImporter($contactImporter);

        $session = $this->orchestrator->startSession(
            $this->organization,
            Platform::Bexio,
            $this->user->id,
        );

        $rowsByType = [
            'accounts' => collect([new AccountImportRow(1, '1020', 'Bank', 'asset')]),
            'contacts' => collect([new ContactImportRow(1, 'customer', 'Test')]),
        ];

        $results = $this->orchestrator->executeAll($session, $rowsByType, $this->organization);

        $session->refresh();
        $this->assertSame(ImportStatus::PartiallyCompleted, $session->status);
    }
}
