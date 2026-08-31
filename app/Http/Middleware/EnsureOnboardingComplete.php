<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user?->resolveCurrentOrganization();
        $isOrganizationOwner = $organization !== null
            && $user->organizations()
                ->whereKey($organization->id)
                ->wherePivot('role', Role::Owner->value)
                ->exists();

        if ($user?->onboarding_completed_at === null && $isOrganizationOwner) {
            return redirect()->route('onboarding.wizard');
        }

        return $next($request);
    }
}
