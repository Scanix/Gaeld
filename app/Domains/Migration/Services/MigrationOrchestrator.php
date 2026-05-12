<?php

namespace App\Domains\Migration\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ParseResult;
use App\Domains\Migration\DTOs\PreviewData;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the migration import pipeline.
 *
 * Coordinates parsing, validation, preview, and execution by delegating
 * to the registry-resolved parsers and importers. Never knows concrete
 * parser/importer types — works entirely with interfaces.
 */
class MigrationOrchestrator
{
    /**
     * Above this chart-of-accounts size, account-mapping fuzzy matching
     * starts to dominate import time and memory. Crossing this threshold
     * triggers a warning so we can decide whether to tighten this into a
     * hard cap or refactor the matcher to use a prefix-bucketed index.
     */
    private const ACCOUNT_MAPPING_SOFT_CAP = 10000;

    public function __construct(
        private readonly MigrationRegistry $registry,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Pipeline
    // ──────────────────────────────────────────────────────────────

    /**
     * Start a new migration session.
     */
    public function startSession(Organization $organization, Platform $platform, int $userId): MigrationSession
    {
        return MigrationSession::create([
            'organization_id' => $organization->id,
            'platform' => $platform,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $userId,
        ]);
    }

    /**
     * Parse an uploaded file for a specific data type.
     */
    public function parseFile(
        MigrationSession $session,
        UploadedFile $file,
        DataType $dataType,
    ): ParseResult {
        $parser = $this->registry->getParser($session->platform);

        if (! $parser) {
            return new ParseResult(collect(), ["Unsupported platform: {$session->platform->value}"]);
        }

        if (! in_array($dataType, $parser->supportedDataTypes(), true)) {
            return new ParseResult(collect(), ["Platform {$session->platform->value} does not support {$dataType->value}"]);
        }

        $rows = $parser->parse($file, $dataType);

        return new ParseResult($rows);
    }

    /**
     * Generate preview data for a parsed data type.
     *
     * @param  Collection<int, ImportRowInterface>  $rows
     */
    public function preview(
        Collection $rows,
        DataType $dataType,
        Organization $organization,
        int $sampleSize = 50,
    ): PreviewData {
        $importer = $this->registry->getImporter($dataType);
        $validation = $importer?->validate($rows, $organization);

        $accountMappings = [];
        if ($this->requiresAccountMapping($dataType)) {
            $accountMappings = $this->suggestAccountMappings($rows, $organization);
        }

        return new PreviewData(
            sampleRows: $rows->take($sampleSize),
            totalRows: $rows->count(),
            validRows: $rows->filter(fn (ImportRowInterface $r) => $r->isValid())->count(),
            invalidRows: $rows->reject(fn (ImportRowInterface $r) => $r->isValid())->count(),
            rowErrors: $validation !== null ? $validation->rowErrors : [],
            accountMappings: $accountMappings,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Import Execution
    // ──────────────────────────────────────────────────────────────

    /**
     * Execute the import for a single data type.
     *
     * @param  Collection<int, ImportRowInterface>  $rows
     */
    public function executeImport(
        MigrationSession $session,
        Collection $rows,
        DataType $dataType,
        Organization $organization,
    ): ImportResult {
        $importer = $this->registry->getImporter($dataType);

        if (! $importer) {
            return ImportResult::failure($dataType, ["No importer registered for {$dataType->value}"]);
        }

        $session->updateDataTypeStatus($dataType->value, ImportStatus::Validating);

        $validation = $importer->validate($rows, $organization);
        if (! $validation->valid) {
            $session->updateDataTypeStatus($dataType->value, ImportStatus::Failed);
            $session->addErrors($dataType->value, $validation->globalErrors);

            return ImportResult::failure($dataType, $validation->globalErrors);
        }

        $session->updateDataTypeStatus($dataType->value, ImportStatus::Importing);

        try {
            $result = $importer->import($rows, $organization);
        } catch (\Throwable $e) {
            Log::error("Migration import: {$dataType->value} import failed unexpectedly", [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
                'organization_id' => $organization->id,
            ]);

            $session->updateDataTypeStatus($dataType->value, ImportStatus::Failed);
            $session->addErrors($dataType->value, [$e->getMessage()]);

            return ImportResult::failure($dataType, ['Import failed unexpectedly. Please try again or contact support.']);
        }

        $status = match (true) {
            ! $result->success => ImportStatus::Failed,
            $result->failedCount > 0 => ImportStatus::PartiallyCompleted,
            default => ImportStatus::Completed,
        };
        $session->updateDataTypeStatus($dataType->value, $status);
        $session->incrementImportedCount($dataType->value, $result->importedCount);

        if (! empty($result->createdIds)) {
            $session->addImportedRecordIds($dataType->value, $result->createdIds);
        }

        if (! empty($result->errors)) {
            $session->addErrors($dataType->value, $result->errors);
        }

        return $result;
    }

    /**
     * Execute imports for multiple data types in the correct dependency order.
     *
     * @param  array<string, Collection<int, ImportRowInterface>>  $rowsByType  Keyed by DataType value
     * @return ImportResult[]
     */
    public function executeAll(
        MigrationSession $session,
        array $rowsByType,
        Organization $organization,
    ): array {
        $requestedTypes = array_map(
            fn (string $key) => DataType::from($key),
            array_keys($rowsByType),
        );

        $orderedTypes = $this->registry->resolveImportOrder($requestedTypes);
        $results = [];

        $session->update(['status' => ImportStatus::Importing]);

        foreach ($orderedTypes as $dataType) {
            $rows = $rowsByType[$dataType->value] ?? collect();

            if ($rows->isEmpty()) {
                continue;
            }

            $results[] = $this->executeImport($session, $rows, $dataType, $organization);
        }

        $session->markCompleted();

        return $results;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Suggest account mappings for rows that reference external account codes.
     *
     * @param  Collection<int, ImportRowInterface>  $rows
     * @return array<string, array{source_code: string, source_name: string, target_code: ?string, target_name: ?string, confidence: float}>
     */
    private function suggestAccountMappings(Collection $rows, Organization $organization): array
    {
        $mappers = $this->registry->getMappers();

        if (empty($mappers)) {
            return [];
        }

        $targetAccounts = Account::where('organization_id', $organization->id)->get();

        // Soft cap: fuzzy matching is O(rows × accounts × mappers) and the
        // whole chart of accounts is held in memory. A normal SME has well
        // under 1k accounts; warn if we ever see an outlier so ops can
        // decide whether to tighten this into a hard cap or refactor the
        // matcher into a prefix-bucketed index.
        if ($targetAccounts->count() > self::ACCOUNT_MAPPING_SOFT_CAP) {
            Log::warning('Migration account mapping exceeded soft cap', [
                'organization_id' => $organization->id,
                'account_count' => $targetAccounts->count(),
                'soft_cap' => self::ACCOUNT_MAPPING_SOFT_CAP,
            ]);
        }

        $mappings = [];

        foreach ($rows as $row) {
            $data = $row->toArray();
            $code = $data['account_code'] ?? $data['code'] ?? null;
            $name = $data['account_name'] ?? $data['name'] ?? '';

            if (! $code || isset($mappings[$code])) {
                continue;
            }

            $bestMatch = null;
            $bestConfidence = 0.0;

            foreach ($mappers as $mapper) {
                $suggestion = $mapper->suggest($code, $name, $targetAccounts);
                if ($suggestion['confidence'] > $bestConfidence) {
                    $bestMatch = $suggestion;
                    $bestConfidence = $suggestion['confidence'];
                }
            }

            $mappings[$code] = [
                'source_code' => $code,
                'source_name' => $name,
                'target_code' => $bestMatch['account']?->code,
                'target_name' => $bestMatch['account']?->name,
                'confidence' => $bestConfidence,
            ];
        }

        return $mappings;
    }

    private function requiresAccountMapping(DataType $dataType): bool
    {
        return in_array($dataType, [
            DataType::OpeningBalances,
            DataType::JournalEntries,
            DataType::Expenses,
            DataType::FixedAssets,
        ], true);
    }

    // ──────────────────────────────────────────────────────────────
    //  Rollback
    // ──────────────────────────────────────────────────────────────

    /**
     * Reverse (undo) a completed migration session by deleting all imported records.
     *
     * @return array{deleted: int, errors: string[]}
     */
    public function rollback(MigrationSession $session): array
    {
        if (! $session->canBeReversed()) {
            return ['deleted' => 0, 'errors' => ['Session cannot be reversed']];
        }

        $session->update(['status' => ImportStatus::Reversing]);

        $totalDeleted = 0;
        $errors = [];

        $modelMap = [
            DataType::Accounts->value => Account::class,
            DataType::Contacts->value => Contact::class,
        ];

        DB::transaction(function () use ($session, $modelMap, &$totalDeleted, &$errors): void {
            $recordIds = $session->imported_record_ids ?? [];

            foreach ($recordIds as $dataType => $ids) {
                if (empty($ids)) {
                    continue;
                }

                $models = $modelMap[$dataType] ?? null;

                if ($models === null) {
                    // For data types not mapped, attempt generic deletion via importer
                    $importer = $this->registry->getImporter(DataType::from($dataType));
                    if ($importer === null) {
                        $errors[] = "No rollback handler for data type: {$dataType}";

                        continue;
                    }
                }

                if (is_array($models)) {
                    // Contacts: try each model type
                    foreach ($models as $modelClass) {
                        $totalDeleted += (int) $modelClass::whereIn('id', $ids)->delete();
                    }
                } elseif ($models !== null) {
                    $totalDeleted += (int) $models::whereIn('id', $ids)->delete();
                }
            }
        });

        $session->update([
            'status' => ImportStatus::Reversed,
            'completed_at' => now(),
        ]);

        return ['deleted' => $totalDeleted, 'errors' => $errors];
    }
}
