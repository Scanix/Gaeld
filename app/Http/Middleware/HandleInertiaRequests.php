<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);
        }

        return array_merge(parent::share($request), [
            'auth' => $user ? [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'show_help' => $user->show_help,
                ],
                'currentOrganization' => ($this->currentOrganization->isBound()
                    ? $this->currentOrganization->get()
                    : $user->resolveCurrentOrganization()
                )?->only('id', 'name', 'currency', 'locale'),
                'subscription' => $this->resolveSubscription($user),
                'organizations' => $user->organizations()
                    ->select('organizations.id', 'organizations.name')
                    ->get()
                    ->map(fn ($org) => [
                        'id' => $org->id,
                        'name' => $org->name,
                        'role' => $org->pivot->role,
                    ]),
            ] : null,
            'locale' => App::getLocale(),
            'translations' => fn () => trans('app'),
            'features' => FeatureFlag::all(),
            'docsBaseUrl' => config('docs.base_url'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    private function resolveSubscription($user): ?array
    {
        if (! FeatureFlag::isSaas()) {
            return null;
        }

        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user?->resolveCurrentOrganization();

        $sub = $org?->activeSubscription;

        if (! $sub) {
            return null;
        }

        return [
            'status' => $sub->status,
            'plan_slug' => $sub->plan->slug ?? null,
            'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
            'ends_at' => $sub->ends_at?->toDateString(),
        ];
    }
}
