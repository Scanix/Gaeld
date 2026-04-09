<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->remove('X-Powered-By');

        if (! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if (! $response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (! $response->headers->has('Permissions-Policy')) {
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            // Horizon uses fonts.bunny.net (not Google Fonts) and Vue requires unsafe-eval.
            $isHorizon = $request->is('horizon') || $request->is('horizon/*');
            $scriptSrc = $isHorizon
                ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://www.googletagmanager.com"
                : "script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.googletagmanager.com";

            $response->headers->set(
                'Content-Security-Policy',
                implode('; ', [
                    "default-src 'self'",
                    $scriptSrc,
                    "style-src 'self' 'unsafe-inline' https://tagmanager.google.com https://fonts.googleapis.com https://fonts.bunny.net",
                    "img-src 'self' data: https://www.googletagmanager.com https://www.google-analytics.com https://*.google-analytics.com https://*.googletagmanager.com",
                    "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
                    "connect-src 'self' https://api.stripe.com https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://region1.google-analytics.com",
                    'frame-src https://js.stripe.com https://hooks.stripe.com',
                    "frame-ancestors 'none'",
                ]),
            );
        }

        return $response;
    }
}
