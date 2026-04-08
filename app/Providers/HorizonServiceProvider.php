<?php

namespace App\Providers;

use App\Domains\Organizations\Enums\Role;
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
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            $org = $user->resolveCurrentOrganization();

            if (! $org) {
                return false;
            }

            $pivot = $org->users()->where('users.id', $user->id)->first()?->pivot;

            return $pivot && $pivot->role === Role::Owner->value;
        });
    }
}
