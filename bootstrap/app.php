<?php

use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use App\Domains\Organizations\Models\Organization;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\Api\HandleApiIdempotency;
use App\Http\Middleware\DisableThrottleInTesting;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureApiOrganization;
use App\Http\Middleware\EnsureHasOrganization;
use App\Http\Middleware\EnsureOrganizationTwoFactor;
use App\Http\Middleware\FakeTimeMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetGuestLocale;
use App\Support\FeatureFlag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse proxies (Pangolin, Coolify, Traefik, nginx, Cloudflare,
        // ...) so X-Forwarded-Proto / -For / -Host / -Port headers are honoured
        // when generating URLs and detecting HTTPS.
        //
        // NOTE: this closure runs before LoadConfiguration, so config() is not
        // yet available — we must read env() directly. Docker deployments pass
        // env vars via `docker-compose environment:` / `env_file:` which end up
        // in the OS env, so env() works even under `php artisan config:cache`.
        // See config/proxy.php for the documented contract.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (! empty($trustedProxies)) {
            $at = $trustedProxies === '*'
                ? '*'
                : array_map('trim', explode(',', $trustedProxies));

            $middleware->trustProxies(at: $at);
        }

        $middleware->prepend(AddSecurityHeaders::class);

        $middleware->web(append: [
            SetGuestLocale::class,
            HandleInertiaRequests::class,
            FakeTimeMiddleware::class,
        ]);

        $middleware->alias([
            'org' => EnsureHasOrganization::class,
            'org-2fa' => EnsureOrganizationTwoFactor::class,
            'api-org' => EnsureApiOrganization::class,
            'api-idempotency' => HandleApiIdempotency::class,
            'subscription' => EnsureActiveSubscription::class,
        ]);

        // Disable rate limiting only when explicitly opted-in during testing/local dev
        if (in_array(env('APP_ENV'), ['testing', 'local'], true) && (bool) env('DISABLE_THROTTLE', false)) {
            $middleware->alias([
                'org' => EnsureHasOrganization::class,
                'org-2fa' => EnsureOrganizationTwoFactor::class,
                'api-org' => EnsureApiOrganization::class,
                'api-idempotency' => HandleApiIdempotency::class,
                'subscription' => EnsureActiveSubscription::class,
                'throttle' => DisableThrottleInTesting::class,
            ]);
        }

        $middleware->redirectGuestsTo(static function () {
            if (FeatureFlag::isSaas()) {
                return route('login');
            }

            return Organization::exists() ? route('login') : route('setup.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Convert invalid UUID queries (e.g. /banking/6 instead of /banking/{uuid})
        // into 404 responses instead of 500 errors.
        $exceptions->renderable(function (QueryException $e) {
            if ($e->getCode() === '22P02' && str_contains($e->getMessage(), 'uuid')) {
                abort(404);
            }
        });

        $exceptions->renderable(function (ModelNotFoundException $e) {
            if (request()->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                    'code' => 'not_found',
                ], 404);
            }
        });

        $exceptions->renderable(function (ValidationException $e) {
            if (request()->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'validation_error',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e) {
            if (request()->is('api/*')) {
                Log::channel('security')->warning('API authentication failed', [
                    'method' => request()->method(),
                    'path' => request()->path(),
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'unauthenticated',
                ], 401);
            }
        });

        $exceptions->renderable(function (AuthorizationException $e) {
            if (request()->is('api/*')) {
                Log::channel('security')->warning('API authorization failed', [
                    'method' => request()->method(),
                    'path' => request()->path(),
                    'route' => request()->route()?->getName(),
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                    'code' => 'forbidden',
                ], 403);
            }
        });

        $exceptions->renderable(function (FiscalYearClosedException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'fiscal_year_closed',
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (DomainException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'domain_error',
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (HttpExceptionInterface $e) {
            if (request()->is('api/*')) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    403 => 'forbidden',
                    404 => 'not_found',
                    429 => 'rate_limit_exceeded',
                    default => 'http_error',
                };

                if ($status === 403) {
                    Log::channel('security')->warning('API authorization failed', [
                        'method' => request()->method(),
                        'path' => request()->path(),
                        'route' => request()->route()?->getName(),
                        'ip' => request()->ip(),
                    ]);
                }

                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                    'code' => $code,
                ], $status, $e->getHeaders());
            }

            if (request()->expectsJson()) {
                return null;
            }

            // Redirect unverified users to email verification instead of showing error page
            $user = request()->user();
            if ($e->getStatusCode() === 403
                && $user
                && $user instanceof MustVerifyEmail
                && ! $user->hasVerifiedEmail()
            ) {
                return redirect()->route('verification.notice');
            }

            return Inertia::render('Error', [
                'status' => $e->getStatusCode(),
            ])->toResponse(request())->setStatusCode($e->getStatusCode());
        });
    })->create();
