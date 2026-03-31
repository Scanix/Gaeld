<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets Carbon::setTestNow() from a file, allowing Playwright E2E tests
 * to simulate a specific date (e.g. fiscal year 2025 while running in 2026).
 *
 * Only active when APP_ENV is "testing" AND the file exists.
 * Write the desired datetime to: storage/framework/testing/fake-now.txt
 */
class FakeTimeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            $path = storage_path('framework/testing/fake-now.txt');

            if (file_exists($path)) {
                $datetime = trim(file_get_contents($path));
                if ($datetime) {
                    Carbon::setTestNow($datetime);
                }
            }
        }

        $response = $next($request);

        // Reset so StartSession (outer middleware) uses real time for
        // cookie expiration — otherwise the browser discards "past" cookies.
        Carbon::setTestNow();

        return $response;
    }
}
