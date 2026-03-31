<?php

use App\Domains\Organizations\Models\Organization;
use App\Http\Middleware\DisableThrottleInTesting;
use App\Http\Middleware\EnsureApiOrganization;
use App\Http\Middleware\EnsureHasOrganization;
use App\Http\Middleware\EnsureOrganizationTwoFactor;
use App\Http\Middleware\FakeTimeMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        $middleware->web(append: [
            HandleInertiaRequests::class,
            FakeTimeMiddleware::class,
        ]);

        $middleware->alias([
            'org' => EnsureHasOrganization::class,
            'org-2fa' => EnsureOrganizationTwoFactor::class,
            'api-org' => EnsureApiOrganization::class,
        ]);

        // Disable rate limiting in testing environment (Docker test stack)
        if (env('APP_ENV') === 'testing') {
            $middleware->alias([
                'org' => EnsureHasOrganization::class,
                'org-2fa' => EnsureOrganizationTwoFactor::class,
                'api-org' => EnsureApiOrganization::class,
                'throttle' => DisableThrottleInTesting::class,
            ]);
        }

        $middleware->redirectGuestsTo(static function () {
            return Organization::exists() ? route('login') : route('setup.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
