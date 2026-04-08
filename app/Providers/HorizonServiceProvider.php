<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::routeMailNotificationsTo(config('mail.from.address'));

        if ($slackWebhook = config('services.slack.horizon_webhook')) {
            Horizon::routeSlackNotificationsTo($slackWebhook, '#gaeld-alerts');
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Only the SaaS admin (SAAS_ADMIN_EMAIL) is allowed in production.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            $adminEmail = config('ee.saas_admin_email');

            return $adminEmail && $user->email === $adminEmail;
        });
    }
}
