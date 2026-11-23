<?php

namespace App\Domains\Api\Services;

use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Invoicing\Models\Invoice;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ExternalReferenceUpsert
{
    public function __construct(private ApiIdempotencyService $idempotency) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $records  Includes soft-deleted records.
     * @param  Closure(TModel|null): JsonResponse  $write
     */
    public function execute(FormRequest $request, string $organizationId, Builder $records, Closure $write): JsonResponse
    {
        return DB::transaction(function () use ($request, $organizationId, $records, $write): JsonResponse {
            $reference = ['external_source' => $request->validated('external_source'), 'external_id' => $request->validated('external_id')];
            $model = $records->getModel();
            // Row locks cannot serialize the first insert for a reference that does not exist yet.
            $key = json_encode([$model->getTable(), $organizationId, $reference], JSON_THROW_ON_ERROR);
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
            $record = $records->where('organization_id', $organizationId)->where($reference)->lockForUpdate()->first();
            $canCreate = Gate::allows('create', $model::class);
            $canEdit = $record !== null && Gate::allows($record instanceof Invoice ? 'editExternalReference' : 'update', $record);
            abort_unless($canCreate || $canEdit, 403);

            if ($record?->getAttribute('deleted_at') !== null) {
                return response()->json(['message' => 'This external reference is reserved.', 'code' => 'external_reference_conflict'], 409);
            }
            if ($record instanceof Invoice && (! $record->status->isEditable() || $record->archived_at !== null)) {
                return response()->json(['message' => 'Only active draft invoices can be updated.', 'code' => 'invalid_invoice_state'], 409);
            }

            // A supplied request key replays the response only after checking current access.
            try {
                $token = $request->user()?->currentAccessToken();
                $principal = $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'user:'.$request->user()?->getAuthIdentifier();
                $reservation = $this->idempotency->reserve($request, $organizationId, null, $principal);
            } catch (ApiIdempotencyConflictException $exception) {
                return response()->json(['message' => $exception->getMessage(), 'code' => 'idempotency_conflict'], 409);
            }
            if ($reservation?->replay) {
                abort_unless($reservation->record->response_status === 201 ? $canCreate : $canEdit, 403);
                if ($record === null || data_get($reservation->record->response_body, 'data.id') !== $record->getRouteKey()) {
                    return response()->json(['message' => 'The referenced record has changed.', 'code' => 'external_reference_conflict'], 409);
                }

                return $this->idempotency->replay($reservation);
            }
            Gate::authorize($record === null ? 'create' : 'update', $record ?? $model::class);
            $response = $write($record);
            if ($reservation !== null) {
                if ($response->isSuccessful()) {
                    $this->idempotency->complete($reservation, $response);
                } else {
                    $this->idempotency->release($reservation);
                }
            }

            return $response;
        });
    }
}
