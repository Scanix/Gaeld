<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bypass throttle/rate-limiting when APP_ENV=testing.
 *
 * Register this in bootstrap/app.php to replace the default
 * throttle middleware in testing environments:
 *
 *   if (app()->environment('testing')) {
 *       $middleware->alias(['throttle' => DisableThrottleInTesting::class]);
 *   }
 */
class DisableThrottleInTesting
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        return $next($request);
    }
}
