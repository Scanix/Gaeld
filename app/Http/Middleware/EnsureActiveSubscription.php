<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In SaaS mode, ensures the current organization has an active or
 * trialing subscription. Redirects to the billing page if the
 * subscription has expired or is missing.
 *
 * In CE mode this middleware is a no-op — all orgs get full access.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FeatureFlag::isSaas()) {
            return $next($request);
        }

        $org = $request->user()?->resolveCurrentOrganization();

        if (! $org) {
            return $next($request);
        }

        $subscription = $org->activeSubscription ?? null;

        if (! $subscription) {
            return redirect()->route('billing.index')
                ->with('error', __('app.subscription_required'));
        }

        // Trial expired but not yet converted
        if ($subscription->status === 'trialing'
            && $subscription->trial_ends_at?->isPast()) {
            return redirect()->route('billing.index')
                ->with('error', __('app.subscription_expired'));
        }

        return $next($request);
    }
}
