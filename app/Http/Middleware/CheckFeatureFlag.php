<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that aborts with 403 when the requested feature flag is disabled.
 *
 * CE: gates at the global/install level only. Per-organization owner toggles
 * (Settings → Modules) only hide UI; they don't block direct URL access.
 *
 * SaaS: additionally enforces per-organization subscription plan gating via
 * the EE SubscriptionFeatureResolver, so a server-wide flag never grants a
 * paid feature to organizations whose plan doesn't include it.
 *
 * Usage: `->middleware('feature:auto_reconciliation')`
 */
class CheckFeatureFlag
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (FeatureFlag::disabled($feature)) {
            abort(403, "Feature '{$feature}' is not enabled.");
        }

        if (FeatureFlag::isSaas()
            && $this->currentOrganization->isBound()
            && ! FeatureFlag::enabledForOrg($feature, $this->currentOrganization->get())
        ) {
            abort(403, "Feature '{$feature}' is not included in your plan.");
        }

        return $next($request);
    }
}
