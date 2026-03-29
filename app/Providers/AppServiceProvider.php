<?php

namespace App\Providers;

use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Policies\ContactPolicy;
use App\Domains\Contacts\Search\ContactSearchProvider;
use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\Search\ExpenseSearchProvider;
use App\Domains\Expenses\Services\TesseractOcrService;
use App\Domains\Invoicing\Search\InvoiceSearchProvider;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Services\GlobalSearchService;
use App\Support\Listeners\AuthAuditSubscriber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

/**
 * Core application service provider — registers bindings, gates, policies, and global search providers.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->singleton(ReceiptOcrInterface::class, TesseractOcrService::class);

        $this->app->singleton(GlobalSearchService::class, function ($app) {
            return new GlobalSearchService(
                $app->make(InvoiceSearchProvider::class),
                $app->make(ContactSearchProvider::class),
                $app->make(ExpenseSearchProvider::class),
            );
        });
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

        Gate::policy(Customer::class, ContactPolicy::class);
        Gate::policy(Supplier::class, ContactPolicy::class);
    }
}
