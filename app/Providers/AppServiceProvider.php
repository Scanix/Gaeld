<?php

namespace App\Providers;

use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Policies\ContactPolicy;
use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\Services\TesseractOcrService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\Listeners\AuthAuditSubscriber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->singleton(ReceiptOcrInterface::class, TesseractOcrService::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        Password::defaults(fn () => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        Event::subscribe(AuthAuditSubscriber::class);
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        Gate::policy(Customer::class, ContactPolicy::class);
        Gate::policy(Supplier::class, ContactPolicy::class);
    }
}
