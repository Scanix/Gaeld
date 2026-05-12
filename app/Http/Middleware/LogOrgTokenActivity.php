<?php

namespace App\Http\Middleware;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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

        if ($token instanceof PersonalAccessToken && $token->type === TokenType::Organization) {
            Log::channel('security')->info('Organization API token request', [
                'token_id' => $token->id,
                'organization_id' => $token->organization_id,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }
}
