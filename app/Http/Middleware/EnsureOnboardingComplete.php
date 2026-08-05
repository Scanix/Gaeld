<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->onboarding_completed_at === null) {
            return redirect()->route('onboarding.wizard');
        }

        return $next($request);
    }
}
