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

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                implode('; ', [
                    "default-src 'self'",
                    "script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.googletagmanager.com",
                    "style-src 'self' 'unsafe-inline' https://tagmanager.google.com https://fonts.googleapis.com",
                    "img-src 'self' data: https://www.googletagmanager.com https://www.google-analytics.com https://*.google-analytics.com https://*.googletagmanager.com",
                    "font-src 'self' https://fonts.gstatic.com",
                    "connect-src 'self' https://api.stripe.com https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://region1.google-analytics.com",
                    'frame-src https://js.stripe.com https://hooks.stripe.com',
                    "frame-ancestors 'none'",
                ]),
            );
        }

        return $response;
    }
}
