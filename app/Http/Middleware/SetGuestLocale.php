<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set the application locale for guest (unauthenticated) visitors.
 *
 * Priority: ?lang= query param → session('guest_locale') → app default.
 * Authenticated users are handled by HandleInertiaRequests instead.
 */
class SetGuestLocale
{
    private const ALLOWED = ['en', 'fr', 'de', 'it'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $lang = $request->query('lang');

        if ($lang && in_array($lang, self::ALLOWED, true)) {
            session(['guest_locale' => $lang]);
            App::setLocale($lang);
        } elseif ($locale = session('guest_locale')) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
