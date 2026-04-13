<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use App\Support\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

/**
 * Inertia.js middleware: shares common data (user, organization, translations,
 * locale, flash messages) with every Vue page component.
 */
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
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'twoFactor' => fn () => $request->session()->get('twoFactor'),
            'systemMessage' => FeatureFlag::isSaas()
                ? Cache::get('saas:system_message')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAuth(User $user): array
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
                'notification_preferences' => $user->notification_preferences ?? [],
            ],
            'currentOrganization' => fn () => $this->resolveCurrentOrganization($user),
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
            'ocr_quota' => fn () => $this->resolveOcrQuota($user),
            'notifications_unread_count' => fn () => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSubscription(User $user): ?array
    {
        if (! FeatureFlag::isSaas()) {
            return null;
        }

        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

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

    /** @return array{ocr_scans_today: int, ocr_daily_limit: int} */
    private function resolveOcrQuota(User $user): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return ['ocr_scans_today' => 0, 'ocr_daily_limit' => config('services.ocr.daily_limit', 3)];
        }

        $orgId = $org->id;
        $dailyKey = "ocr_daily:{$orgId}:".now()->toDateString();
        $scansToday = (int) Cache::get($dailyKey, 0);

        $limit = config('services.ocr.daily_limit', 3);
        if (FeatureFlag::isSaas()) {
            $plan = $org->activeSubscription?->plan;
            if ($plan && isset($plan->max_ocr_scans_per_day)) {
                $limit = (int) $plan->max_ocr_scans_per_day;
            }
        }

        return ['ocr_scans_today' => $scansToday, 'ocr_daily_limit' => $limit];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCurrentOrganization(User $user): ?array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return null;
        }

        $data = $org->only('id', 'name', 'currency', 'locale', 'require_two_factor');
        $data['closed_fiscal_years'] = $org->closed_fiscal_years ?? [];
        $data['business_type'] = $org->business_type?->value;

        return $data;
    }

    private function resolveCurrentRole(User $user): ?string
    {
        return $user->getRoleNames()->first();
    }

    /**
     * @return string[]
     */
    private function resolvePermissions(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->toArray();
    }
}
