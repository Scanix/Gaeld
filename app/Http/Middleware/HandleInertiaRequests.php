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
            'auth' => $user ? $this->resolveAuth($user) : null,
            'locale' => App::getLocale(),
            'translations' => fn () => trans('app'),
            'features' => FeatureFlag::all(),
            'docsBaseUrl' => config('docs.base_url'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'twoFactor' => fn () => $request->session()->get('twoFactor'),
        ]);
    }

    private function resolveAuth($user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'show_help' => $user->show_help,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'has_passkeys' => $user->webAuthnCredentials()->exists(),
            ],
            'currentOrganization' => ($this->currentOrganization->isBound()
                ? $this->currentOrganization->get()
                : $user->resolveCurrentOrganization()
            )?->only('id', 'name', 'currency', 'locale', 'require_two_factor'),
            'subscription' => $this->resolveSubscription($user),
            'role' => fn () => $this->resolveCurrentRole($user),
            'permissions' => fn () => $this->resolvePermissions($user),
            'organizations' => $user->organizations()
                ->select('organizations.id', 'organizations.name')
                ->get()
                ->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'role' => $org->pivot->role,
                ]),
            'is_saas_admin' => FeatureFlag::isSaas()
                && config('ee.saas_admin_email')
                && $user->email === config('ee.saas_admin_email'),
        ];
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
            'plan_slug' => $sub->plan?->slug,
            'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
            'ends_at' => $sub->ends_at?->toDateString(),
        ];
    }

    private function resolveCurrentRole($user): ?string
    {
        return $user->getRoleNames()->first();
    }

    /**
     * @return string[]
     */
    private function resolvePermissions($user): array
    {
        return $user->getAllPermissions()->pluck('name')->toArray();
    }
}
