<?php

namespace App\Domains\Migration\Controllers;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Jobs\ProcessMigrationImport;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Requests\ExecuteMigrationRequest;
use App\Domains\Migration\Requests\StartMigrationRequest;
use App\Domains\Migration\Requests\UploadMigrationFileRequest;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Migration\Services\MigrationRegistry;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MigrationController extends Controller
{
    public function __construct(
        private readonly MigrationOrchestrator $orchestrator,
        private readonly MigrationRegistry $registry,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * Migration hub — list sessions and available platforms.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', MigrationSession::class);

        $sessions = MigrationSession::where('organization_id', $this->currentOrganization->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Migration/Index', [
            'sessions' => $sessions,
            'platforms' => $this->registry->availablePlatforms(),
        ]);
    }

    /**
     * Start a new migration session for a chosen platform.
     */
    public function store(StartMigrationRequest $request): RedirectResponse
    {
        $this->authorize('create', MigrationSession::class);

        $platform = Platform::from($request->validated('platform'));
        $session = $this->orchestrator->startSession(
            $this->currentOrganization->get(),
            $platform,
            $request->user()->id,
        );

        return redirect()->route('migration.show', $session)
            ->with('success', __('migration.session_started'));
    }

    /**
     * Show a migration session with upload/preview/execute steps.
     */
    public function show(MigrationSession $session): Response
    {
        $this->authorize('view', $session);

        $parser = $this->registry->getParser($session->platform);

        return Inertia::render('Migration/Show', [
            'session' => $session,
            'supportedDataTypes' => $parser?->supportedDataTypes() ?? [],
            'acceptedExtensions' => $parser?->acceptedExtensions() ?? [],
        ]);
    }

    /**
     * Upload and parse a file for a specific data type.
     */
    public function upload(UploadMigrationFileRequest $request, MigrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $dataType = DataType::from($request->validated('data_type'));
        $file = $request->file('file');

        // Set column mapping for generic CSV parser if provided
        $parser = $this->registry->getParser($session->platform);
        if ($parser instanceof \App\Domains\Migration\Parsers\GenericCsvParser) {
            $mapping = $request->validated('column_mapping');
            $delimiter = $request->validated('delimiter', ',');
            if ($mapping) {
                $parser->setColumnMapping($mapping);
            }
            $parser->setDelimiter($delimiter);
        }

        $parseResult = $this->orchestrator->parseFile($session, $file, $dataType);

        if (! $parseResult->isSuccessful()) {
            return redirect()->back()
                ->with('error', implode(', ', $parseResult->errors));
        }

        // Store parsed rows in session storage for preview/execution
        $cacheKey = "migration:{$session->id}:{$dataType->value}";
        cache()->put($cacheKey, $parseResult->rows, now()->addHours(2));

        $preview = $this->orchestrator->preview(
            $parseResult->rows,
            $dataType,
            $this->currentOrganization->get(),
        );

        return redirect()->route('migration.show', $session)
            ->with('preview', [
                'data_type' => $dataType->value,
                'preview' => $preview,
            ]);
    }

    /**
     * Execute the import for selected data types.
     */
    public function execute(ExecuteMigrationRequest $request, MigrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $dataTypes = array_map(
            fn (string $type) => DataType::from($type),
            $request->validated('data_types'),
        );

        // Collect cached parsed rows
        $rowsByType = [];
        $totalRows = 0;
        foreach ($dataTypes as $dataType) {
            $cacheKey = "migration:{$session->id}:{$dataType->value}";
            $rows = cache()->get($cacheKey);

            if ($rows === null) {
                return redirect()->back()
                    ->with('error', __('migration.expired_upload', ['type' => $dataType->value]));
            }

            $rowsByType[$dataType->value] = $rows;
            $totalRows += $rows->count();
        }

        // Dispatch to queue for large imports, else execute synchronously
        if ($totalRows > 500) {
            ProcessMigrationImport::dispatch($session, $rowsByType);

            return redirect()->route('migration.show', $session)
                ->with('info', __('migration.import_queued'));
        }

        $results = $this->orchestrator->executeAll(
            $session,
            $rowsByType,
            $this->currentOrganization->get(),
        );

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        if ($failedCount === 0) {
            return redirect()->route('migration.show', $session)
                ->with('success', __('migration.import_complete', ['count' => $successCount]));
        }

        return redirect()->route('migration.show', $session)
            ->with('warning', __('migration.import_partial', ['success' => $successCount, 'failed' => $failedCount]));
    }

    /**
     * Delete a migration session.
     */
    public function destroy(MigrationSession $session): RedirectResponse
    {
        $this->authorize('delete', $session);

        $session->delete();

        return redirect()->route('migration.index')
            ->with('success', __('migration.session_deleted'));
    }

    /**
     * Reverse (undo) all imported records from a completed session.
     */
    public function rollback(MigrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        if (! $session->canBeReversed()) {
            return redirect()->route('migration.show', $session)
                ->with('error', __('migration.rollback_not_available'));
        }

        $result = $this->orchestrator->rollback($session);

        if (! empty($result['errors'])) {
            return redirect()->route('migration.show', $session)
                ->with('warning', __('migration.rollback_partial', ['deleted' => $result['deleted']]));
        }

        return redirect()->route('migration.show', $session)
            ->with('success', __('migration.rollback_complete', ['deleted' => $result['deleted']]));
    }

}
