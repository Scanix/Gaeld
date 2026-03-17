<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = $request->user()?->resolveCurrentOrganization();
        abort_if(! $org, 403, 'No organization found.');

        app()->instance('current_organization', $org);

        return $next($request);
    }
}
