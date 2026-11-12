<?php

namespace App\Http\Middleware;

use App\Domains\Api\Mcp\McpAccess;
use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        abort_unless($request->bearerToken() && McpAccess::acceptsToken($token), 403, 'A personal read-only token is required.');

        $organization = $request->user()->organizations()
            ->where('organizations.id', $token->organization_id)
            ->first();

        abort_unless($organization !== null, 403, 'Organization access denied.');

        app(CurrentOrganization::class)->set($organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}
