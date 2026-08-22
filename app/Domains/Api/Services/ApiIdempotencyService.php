<?php

namespace App\Domains\Api\Services;

use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Models\ApiIdempotencyKey;
use App\Domains\Api\Support\ApiIdempotencyReservation;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ApiIdempotencyService
{
    public function reserve(Request $request, string $organizationId, ?string $fallbackReference): ?ApiIdempotencyReservation
    {
        $headerKey = trim((string) $request->header('Idempotency-Key'));
        $route = (string) ($request->route()?->getName() ?? $request->path());
        $requestKey = $headerKey !== ''
            ? $headerKey
            : ($fallbackReference !== null && $fallbackReference !== ''
                ? "reference:{$route}:{$fallbackReference}"
                : null);

        if ($requestKey === null) {
            return null;
        }

        if (strlen($requestKey) > 255) {
            throw new ApiIdempotencyConflictException('The Idempotency-Key header is too long.');
        }

        $payloadHash = hash('sha256', json_encode([
            'method' => $request->method(),
            'route' => $route,
            'payload' => $this->payloadForHash($request),
        ], JSON_THROW_ON_ERROR));

        $existing = ApiIdempotencyKey::query()
            ->where('organization_id', $organizationId)
            ->where('request_key', $requestKey)
            ->first();

        if ($existing !== null) {
            if ($existing->isExpired()) {
                $existing->delete();
            } else {
                return $this->resolveExisting($existing, $payloadHash, $request, $route);
            }
        }

        try {
            $record = ApiIdempotencyKey::create([
                'organization_id' => $organizationId,
                'request_key' => $requestKey,
                'http_method' => $request->method(),
                'route' => $route,
                'payload_hash' => $payloadHash,
                'expires_at' => now()->addDay(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $record = ApiIdempotencyKey::query()
                ->where('organization_id', $organizationId)
                ->where('request_key', $requestKey)
                ->firstOrFail();

            return $this->resolveExisting($record, $payloadHash, $request, $route);
        }

        return new ApiIdempotencyReservation($record);
    }

    public function complete(ApiIdempotencyReservation $reservation, JsonResponse $response): void
    {
        $reservation->record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getData(true),
        ]);
    }

    public function replay(ApiIdempotencyReservation $reservation): JsonResponse
    {
        return response()->json(
            $reservation->record->response_body,
            $reservation->record->response_status ?? 200,
        );
    }

    public function release(ApiIdempotencyReservation $reservation): void
    {
        $reservation->record->delete();
    }

    private function resolveExisting(
        ApiIdempotencyKey $record,
        string $payloadHash,
        Request $request,
        string $route,
    ): ApiIdempotencyReservation {
        if ($record->http_method !== $request->method()
            || $record->route !== $route
            || $record->payload_hash !== $payloadHash) {
            throw new ApiIdempotencyConflictException(
                'The idempotency key was already used with a different request.'
            );
        }

        if (! $record->isCompleted()) {
            throw new ApiIdempotencyConflictException(
                'The idempotency key is currently being processed.'
            );
        }

        return new ApiIdempotencyReservation($record, true);
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains($exception->getMessage(), 'api_idempotency_keys');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForHash(Request $request): array
    {
        $payload = $request->except(array_keys($request->allFiles()));
        $files = [];

        foreach ($request->allFiles() as $key => $file) {
            if ($file instanceof UploadedFile) {
                $files[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                    'content_hash' => hash_file('sha256', $file->getRealPath()),
                ];
            }
        }

        if ($files !== []) {
            $payload['_files'] = $files;
        }

        return $payload;
    }
}
