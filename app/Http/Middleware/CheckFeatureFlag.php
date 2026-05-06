<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that aborts with 403 when the requested feature flag is disabled.
 *
 * Gates at the global/install level. Per-organization owner toggles
 * (Settings → Modules) only hide UI; they don't block direct URL access.
 *
 * Usage: `->middleware('feature:auto_reconciliation')`
 */
class CheckFeatureFlag
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (FeatureFlag::disabled($feature)) {
            abort(403, "Feature '{$feature}' is not enabled.");
        }

        return $next($request);
    }
}
