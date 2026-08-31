<?php

namespace App\Http\Middleware\Api;

use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Api\Services\ApiIdempotencyService;
use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HandleApiIdempotency
{
    public function __construct(
        private ApiIdempotencyService $idempotency,
        private CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || $this->isHandledByDomainController($request)) {
            return $next($request);
        }

        if (! $this->currentOrganization->isBound()) {
            return $next($request);
        }

        $startedAt = microtime(true);
        try {
            $reservation = $this->idempotency->reserve(
                $request,
                $this->currentOrganization->id(),
                $this->fallbackReference($request),
            );
        } catch (ApiIdempotencyConflictException $exception) {
            $this->logOutcome($request, 409, false, $startedAt);

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'idempotency_conflict',
            ], 409);
        }

        if ($reservation === null) {
            return $next($request);
        }

        if ($reservation->replay) {
            $this->logOutcome($request, $reservation->record->response_status ?? 200, true, $startedAt);

            return $this->idempotency->replay($reservation);
        }

        try {
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
                $this->idempotency->complete($reservation, $response);
            } else {
                $this->idempotency->release($reservation);
            }
            $this->logOutcome($request, $response->getStatusCode(), false, $startedAt);

            return $response;
        } catch (Throwable $exception) {
            $this->idempotency->release($reservation);
            $this->logOutcome($request, 500, false, $startedAt);

            throw $exception;
        }
    }

    private function isHandledByDomainController(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_starts_with($routeName, 'api.journal-entries.')
            || $routeName === 'api.journal-entries.store'
            || $routeName === 'api.bank-accounts.import-camt053'
            || in_array($routeName, [
                'api.tokens.store',
                'api.org-tokens.store',
                'api.webhooks.store',
                'api.webhooks.regenerate-secret',
            ], true);
    }

    private function fallbackReference(Request $request): string
    {
        foreach (['reference', 'number', 'external_reference'] as $field) {
            $value = $request->input($field);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && isset($parameter->id)) {
                return (string) $parameter->id;
            }
        }

        foreach ($request->allFiles() as $file) {
            if (is_array($file)) {
                continue;
            }

            $contents = file_get_contents($file->getRealPath());
            if ($contents !== false) {
                return 'file:'.hash('sha256', $contents);
            }
        }

        return 'payload:'.hash('sha256', $request->getContent());
    }

    private function logOutcome(Request $request, int $status, bool $replayed, float $startedAt): void
    {
        $token = $request->user()?->currentAccessToken();

        $tokenType = $token instanceof PersonalAccessToken ? $token->type->value : null;

        Log::channel('security')->info('API mutation completed', [
            'organization_id' => $this->currentOrganization->id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'token_type' => $tokenType,
            'status' => $status,
            'replayed' => $replayed,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
