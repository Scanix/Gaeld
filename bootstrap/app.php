<?php

use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use App\Domains\Organizations\Models\Organization;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\DisableThrottleInTesting;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureApiOrganization;
use App\Http\Middleware\EnsureHasOrganization;
use App\Http\Middleware\EnsureOrganizationTwoFactor;
use App\Http\Middleware\FakeTimeMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetGuestLocale;
use App\Support\FeatureFlag;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
            'subscription' => EnsureActiveSubscription::class,
        ]);

        // Disable rate limiting in testing environment (Docker test stack)
        if (env('APP_ENV') === 'testing') {
            $middleware->alias([
                'org' => EnsureHasOrganization::class,
                'org-2fa' => EnsureOrganizationTwoFactor::class,
                'api-org' => EnsureApiOrganization::class,
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

        $exceptions->renderable(function (FiscalYearClosedException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (DomainException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (HttpExceptionInterface $e) {
            if (request()->is('api/*') || request()->expectsJson()) {
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
