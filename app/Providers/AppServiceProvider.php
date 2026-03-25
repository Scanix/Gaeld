<?php

namespace App\Providers;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\Services\TesseractOcrService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\Listeners\AuthAuditSubscriber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->singleton(ReceiptOcrInterface::class, TesseractOcrService::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        Event::subscribe(AuthAuditSubscriber::class);
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}
