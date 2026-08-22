<?php

namespace App\Http\Middleware;

use App\Domains\Api\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Audit-log every API request authenticated by an organization token.
 *
 * Org tokens grant cross-user access scoped only by ability/permission, so
 * each call must leave a trace (DSG Art. 8) on the dedicated security channel.
 *
 * Personal tokens are intentionally not logged here — the acting user is
 * already covered by Spatie\Activitylog on individual model writes.
 */
class LogOrgTokenActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logRequest($request, $token, $user->id, $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500, $startedAt);

            throw $exception;
        }

        $this->logRequest($request, $token, $user->id, $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function logRequest(Request $request, PersonalAccessToken $token, int|string $userId, int $status, float $startedAt): void
    {
        Log::channel('security')->info('API token request', [
            'token_id' => $token->id,
            'organization_id' => $token->organization_id,
            'user_id' => $userId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'token_type' => $token->type->value,
            'status' => $status,
            'outcome' => $status >= 400 ? 'failure' : 'success',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
