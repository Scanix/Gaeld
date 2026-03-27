<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasOrganization
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $org = $request->user()?->resolveCurrentOrganization();

        if (! $org) {
            return redirect()->route('onboarding');
        }

        $this->currentOrganization->set($org);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        return $next($request);
    }
}
