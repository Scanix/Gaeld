<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use App\Domains\Accounting\Exceptions\InvalidEntryDataException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Requests\JournalEntryActionApiRequest;
use App\Domains\Api\Requests\JournalEntryIndexApiRequest;
use App\Domains\Api\Requests\StoreJournalEntryApiRequest;
use App\Domains\Api\Resources\JournalEntryResource;
use App\Domains\Api\Services\ApiIdempotencyService;
use App\Domains\Api\Services\JournalEntryApiMapper;
use App\Domains\Api\Support\ApiIdempotencyReservation;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON API access to the general journal.
 */
class JournalEntryApiController extends Controller
{
    public function index(JournalEntryIndexApiRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JournalEntry::class);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $entries = JournalEntry::query()
            ->with('lines.account')
            ->when($request->input('status'), function ($query, string $status): void {
                $query->where('is_posted', $status === 'posted');
            })
            ->when($request->input('from'), fn ($query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($request->input('to'), fn ($query, string $date) => $query->whereDate('date', '<=', $date))
            ->when($request->input('reference'), fn ($query, string $reference) => $query->where('reference', $reference))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return JournalEntryResource::collection($entries);
    }

    public function show(
        JournalEntry $journalEntry,
        CurrentOrganization $currentOrganization,
    ): JournalEntryResource {
        $this->ensureOrganization($journalEntry, $currentOrganization->id());
        $this->authorize('view', $journalEntry);

        return new JournalEntryResource($journalEntry->load('lines.account'));
    }

    public function store(
        StoreJournalEntryApiRequest $request,
        CurrentOrganization $currentOrganization,
        JournalEntryApiMapper $mapper,
        LedgerService $ledger,
        ApiIdempotencyService $idempotency,
    ): JournalEntryResource|JsonResponse {
        $this->authorize('create', JournalEntry::class);

        $reservation = null;

        try {
            $validated = $request->validated();
            $reservation = $idempotency->reserve(
                $request,
                $currentOrganization->id(),
                $validated['reference'] ?? null,
            );

            if ($reservation === null) {
                return response()->json([
                    'message' => 'An Idempotency-Key or reference is required for mutations.',
                    'code' => 'idempotency_key_required',
                ], 422);
            }

            if ($reservation->replay) {
                return $idempotency->replay($reservation);
            }

            $entryData = $mapper->toData($validated, $currentOrganization->id());
            $entry = $request->string('status')->value() === 'posted'
                ? $ledger->postEntry($currentOrganization->id(), $entryData)
                : $ledger->createDraft($currentOrganization->id(), $entryData);

            $response = (new JournalEntryResource($entry->load('lines.account')))
                ->response()
                ->setStatusCode(201);

            $idempotency->complete($reservation, $response);

            return $response;
        } catch (\DomainException $exception) {
            $this->releaseReservation($reservation, $idempotency);

            return $this->domainError($exception);
        }
    }

    public function post(
        Request $request,
        JournalEntry $journalEntry,
        CurrentOrganization $currentOrganization,
        LedgerService $ledger,
        ApiIdempotencyService $idempotency,
    ): JsonResponse {
        $this->ensureOrganization($journalEntry, $currentOrganization->id());

        if ($journalEntry->is_posted) {
            $this->authorize('view', $journalEntry);
        } else {
            $this->authorize('post', $journalEntry);
        }

        return $this->executeMutation(
            $request,
            $currentOrganization->id(),
            (string) $journalEntry->reference,
            $idempotency,
            function () use ($journalEntry, $ledger): JsonResponse {
                if ($journalEntry->is_posted) {
                    throw new AlreadyPostedException('Journal entry is already posted.');
                }

                $entry = $ledger->postDraft($journalEntry);

                return (new JournalEntryResource($entry->load('lines.account')))
                    ->response()
                    ->setStatusCode(200);
            },
        );
    }

    public function reverse(
        JournalEntryActionApiRequest $request,
        JournalEntry $journalEntry,
        CurrentOrganization $currentOrganization,
        LedgerService $ledger,
        ApiIdempotencyService $idempotency,
    ): JsonResponse {
        $this->ensureOrganization($journalEntry, $currentOrganization->id());
        $this->authorize('reverse', $journalEntry);

        return $this->executeMutation(
            $request,
            $currentOrganization->id(),
            'REV-'.(string) $journalEntry->reference,
            $idempotency,
            function () use ($request, $journalEntry, $ledger): JsonResponse {
                $entry = $ledger->reverseEntry(
                    $journalEntry->load('lines.account'),
                    $request->validated()['description'] ?? null,
                    'api',
                );

                return (new JournalEntryResource($entry->load('lines.account')))
                    ->response()
                    ->setStatusCode(200);
            },
        );
    }

    public function destroy(
        Request $request,
        JournalEntry $journalEntry,
        CurrentOrganization $currentOrganization,
        LedgerService $ledger,
        ApiIdempotencyService $idempotency,
    ): JsonResponse {
        $this->ensureOrganization($journalEntry, $currentOrganization->id());
        $this->authorize('delete', $journalEntry);

        return $this->executeMutation(
            $request,
            $currentOrganization->id(),
            'DELETE-'.(string) $journalEntry->id,
            $idempotency,
            function () use ($journalEntry, $ledger): JsonResponse {
                $ledger->deleteDraft($journalEntry);

                return response()->json(null, 204);
            },
        );
    }

    /**
     * @param  Closure(): JsonResponse  $operation
     */
    private function executeMutation(
        Request $request,
        string $organizationId,
        ?string $fallbackReference,
        ApiIdempotencyService $idempotency,
        Closure $operation,
    ): JsonResponse {
        $reservation = null;

        try {
            $reservation = $idempotency->reserve($request, $organizationId, $fallbackReference);

            if ($reservation === null) {
                return response()->json([
                    'message' => 'An Idempotency-Key or reference is required for mutations.',
                    'code' => 'idempotency_key_required',
                ], 422);
            }

            if ($reservation->replay) {
                return $idempotency->replay($reservation);
            }

            $response = $operation();
            $idempotency->complete($reservation, $response);

            return $response;
        } catch (\DomainException $exception) {
            $this->releaseReservation($reservation, $idempotency);

            return $this->domainError($exception);
        }
    }

    private function ensureOrganization(JournalEntry $journalEntry, string $organizationId): void
    {
        abort_unless($journalEntry->organization_id === $organizationId, 404);
    }

    private function releaseReservation(
        ?ApiIdempotencyReservation $reservation,
        ApiIdempotencyService $idempotency,
    ): void {
        if ($reservation !== null && ! $reservation->replay) {
            $idempotency->release($reservation);
        }
    }

    private function domainError(\DomainException $exception): JsonResponse
    {
        $code = match (true) {
            $exception instanceof ApiIdempotencyConflictException => 'idempotency_conflict',
            $exception instanceof AlreadyPostedException => 'concurrent_transition',
            $exception instanceof DuplicateReferenceException => 'duplicate_reference',
            $exception instanceof FiscalYearClosedException => 'fiscal_year_closed',
            $exception instanceof InvalidEntryDataException => 'invalid_entry_data',
            $exception instanceof UnbalancedEntryException => 'journal_entry_unbalanced',
            default => 'domain_error',
        };

        $message = match ($code) {
            'idempotency_conflict' => $exception->getMessage(),
            'duplicate_reference' => 'The accounting reference is already in use.',
            'journal_entry_unbalanced' => 'The journal entry is not balanced.',
            default => $exception->getMessage(),
        };

        return response()->json([
            'message' => $message,
            'code' => $code,
        ], in_array($code, ['duplicate_reference', 'idempotency_conflict', 'concurrent_transition'], true) ? 409 : 422);
    }
}
