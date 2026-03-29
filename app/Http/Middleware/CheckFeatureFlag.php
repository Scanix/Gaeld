<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that aborts with 403 when the requested feature flag is disabled.
 *
 * Usage: `->middleware('feature:auto_reconciliation')`
 */
class CheckFeatureFlag
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('feature:bank_sync')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (FeatureFlag::disabled($feature)) {
            abort(403, "Feature '{$feature}' is not enabled.");
        }

        return $next($request);
    }
}
