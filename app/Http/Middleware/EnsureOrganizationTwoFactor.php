<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces two-factor authentication when the organization requires it.
 */
class EnsureOrganizationTwoFactor
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip the check when the user is already on the profile page
        // to avoid an infinite redirect loop.
        if ($request->routeIs('profile', 'two-factor.*', 'passkeys.*', 'profile.*')) {
            return $next($request);
        }

        $user = $request->user();
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : null;

        if ($org && $org->require_two_factor && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('profile')
                ->with('error', trans('app.two_factor_required_by_org'));
        }

        return $next($request);
    }
}
