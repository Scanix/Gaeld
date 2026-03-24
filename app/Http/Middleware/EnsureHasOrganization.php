<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
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

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        \Log::info('EnsureHasOrganization: teamId set', [
            'org' => $org->id,
            'user' => $request->user()->id,
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
