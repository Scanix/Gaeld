<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationTwoFactor
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
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
