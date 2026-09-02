<?php

namespace App\Http\Middleware;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\OrganizationDocumentStorageUsage;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use App\Support\Contracts\OrganizationQuotaResolver;
use App\Support\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
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
        $this->denyPrivilegedSupportRequests($request);
        $user = $request->user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);
        }

        return array_merge(parent::share($request), [
            'auth' => $user ? $this->resolveAuth($user, $request) : null,
            'locale' => App::getLocale(),
            'translations' => fn () => trans('app'),
            'features' => fn () => $this->resolveFeatures(),
            'routeCapabilities' => fn () => $this->resolveRouteCapabilities(),
            'docsBaseUrl' => config('docs.base_url'),
            'docsRoutes' => config('docs.routes', []),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'preview' => $request->session()->get('preview'),
                'newToken' => $request->session()->get('newToken'),
                'webhookSecret' => $request->session()->get('webhookSecret'),
                'billing_checkout' => $request->session()->get('billing_checkout'),
            ],
            'twoFactor' => fn () => $request->session()->get('twoFactor'),
            'systemMessage' => FeatureFlag::isSaas()
                ? Cache::get('saas:system_message')
                : null,
            'supportSession' => $this->resolveSupportSession($request),
            'hcaptchaSiteKey' => (string) config('services.hcaptcha.site_key', ''),
        ]);
    }

    private function denyPrivilegedSupportRequests(Request $request): void
    {
        $supportSession = $request->session()->get('saas_support_session');
        if (! is_array($supportSession) || $request->routeIs('saas-admin.support.stop')) {
            return;
        }

        if ($request->is('saas-admin') || $request->is('saas-admin/*') || $request->is('security/*') || $request->is('billing*') || $request->is('profile*') || $request->is('two-factor-challenge*')) {
            abort(403, trans('app.saas_admin_support_privileged_denied'));
        }
    }

    /** @return array<string, mixed>|null */
    private function resolveSupportSession(Request $request): ?array
    {
        if (! FeatureFlag::isSaas() || ! app()->bound('Plugins\\GaeldEE\\Domains\\SaasAdmin\\Services\\SupportSessionService')) {
            return null;
        }

        $session = app('Plugins\\GaeldEE\\Domains\\SaasAdmin\\Services\\SupportSessionService')->current($request);
        if ($session?->expiresAt->isPast()) {
            app('Plugins\\GaeldEE\\Domains\\SaasAdmin\\Services\\SupportSessionService')->expire($request);

            return null;
        }

        if ($session && ! $request->routeIs('saas-admin.support.stop')) {
            $target = User::query()->whereKey($session->targetUserId)->first();
            if ($target) {
                Auth::setUser($target);
            }
        }

        return $session?->toArray();
    }

    /**
     * Resolve feature flags for the current request, applying per-org overrides.
     *
     * @return array<string, bool>
     */
    private function resolveFeatures(): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : null;

        $resolved = [];
        foreach (FeatureFlag::all() as $key => $_) {
            $resolved[$key] = $org
                ? FeatureFlag::enabledForOrg($key, $org)
                : FeatureFlag::enabled($key);
        }

        return $resolved;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function resolveRouteCapabilities(): array
    {
        return [
            'accounting' => [
                'taxDeclarations' => Route::has('accounting.tax-declarations.index'),
                'costCenters' => Route::has('accounting.cost-centers.index'),
                'analyticalReport' => Route::has('accounting.analytical-report.index'),
                'consolidation' => Route::has('accounting.consolidation.index'),
                'exchangeRates' => Route::has('accounting.exchange-rates.index'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAuth(User $user, Request $request): array
    {
        $auth = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'show_help' => $user->show_help,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'has_passkeys' => $user->webAuthnCredentials()->exists(),
                'notification_preferences' => $user->notification_preferences ?? [],
                'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
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
            'notifications_unread_count' => fn () => $user->unreadNotifications()->count(),
        ];

        if (! $request->is('saas-admin') && ! $request->is('saas-admin/*')) {
            $auth['ocr_quota'] = fn (): array => $this->resolveOcrQuota($user);
            $auth['invoice_quota'] = fn (): array => $this->resolveInvoiceMonthlyQuota($user);
            $auth['document_storage_quota'] = fn (): array => $this->resolveDocumentStorageQuota($user);
            $auth['member_quota'] = fn (): array => $this->resolveMemberQuota($user);
        }

        return $auth;
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

        $plan = $sub->getPlan();

        return [
            'status' => $sub->getStatus(),
            'plan_slug' => is_object($plan) ? ($plan->slug ?? null) : null,
            'plan_name' => is_object($plan) ? ($plan->name ?? null) : null,
            'plan_price_chf' => is_object($plan) ? ($plan->price_chf ?? null) : null,
            'plan_is_legacy' => is_object($plan) && method_exists($plan, 'isLegacy') && $plan->isLegacy(),
            'trial_ends_at' => $sub->getTrialEndsAt()?->format('Y-m-d'),
            'ends_at' => $sub->getEndsAt()?->format('Y-m-d'),
        ];
    }

    /** @return array{ocr_scans_today: int, ocr_daily_limit: int, ocr_scans_this_month: int, ocr_monthly_limit: int} */
    private function resolveOcrQuota(User $user): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return [
                'ocr_scans_today' => 0,
                'ocr_daily_limit' => config('services.ocr.daily_limit', 3),
                'ocr_scans_this_month' => 0,
                'ocr_monthly_limit' => -1,
            ];
        }

        $orgId = $org->id;
        $dailyKey = "ocr_daily:{$orgId}:".now()->toDateString();
        $monthlyKey = 'ocr_monthly:'.$orgId.':'.now()->format('Y-m');
        $scansToday = (int) Cache::get($dailyKey, 0);
        $resolver = app(OrganizationQuotaResolver::class);

        return [
            'ocr_scans_today' => $scansToday,
            'ocr_daily_limit' => $resolver->maxOcrScansPerDay($org),
            'ocr_scans_this_month' => (int) Cache::get($monthlyKey, 0),
            'ocr_monthly_limit' => $resolver->maxOcrScansPerMonth($org),
        ];
    }

    /** @return array{invoices_this_month: int, invoice_monthly_limit: int} */
    private function resolveInvoiceMonthlyQuota(User $user): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return ['invoices_this_month' => 0, 'invoice_monthly_limit' => -1];
        }

        $orgId = $org->id;
        $monthlyKey = 'invoices_monthly:'.$orgId.':'.now()->format('Y-m');
        $invoicesThisMonth = (int) Cache::get($monthlyKey, 0);

        $limit = app(OrganizationQuotaResolver::class)->maxInvoicesPerMonth($org);

        return ['invoices_this_month' => $invoicesThisMonth, 'invoice_monthly_limit' => $limit];
    }

    /** @return array{bytes_used: int|null, storage_limit_bytes: int, metered: bool} */
    private function resolveDocumentStorageQuota(User $user): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return ['bytes_used' => null, 'storage_limit_bytes' => -1, 'metered' => false];
        }

        $usage = OrganizationDocumentStorageUsage::query()
            ->where('organization_id', $org->id)
            ->first();

        return [
            'bytes_used' => $usage?->bytes_used,
            'storage_limit_bytes' => app(OrganizationQuotaResolver::class)->maxStorageBytes($org),
            'metered' => $usage !== null,
        ];
    }

    /** @return array{members: int, pending_invitations: int, total: int, member_limit: int} */
    private function resolveMemberQuota(User $user): array
    {
        $org = $this->currentOrganization->isBound()
            ? $this->currentOrganization->get()
            : $user->resolveCurrentOrganization();

        if (! $org) {
            return ['members' => 0, 'pending_invitations' => 0, 'total' => 0, 'member_limit' => -1];
        }

        $members = $org->users()->count();
        $pendingInvitations = $org->invitations()->pending()->count();

        return [
            'members' => $members,
            'pending_invitations' => $pendingInvitations,
            'total' => $members + $pendingInvitations,
            'member_limit' => app(OrganizationQuotaResolver::class)->maxUsers($org),
        ];
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

        $data['fiscal_years'] = FiscalYear::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->orderBy('start_date')
            ->get()
            ->map(fn ($fy) => [
                'id' => $fy->id,
                'name' => $fy->name,
                'start_date' => $fy->start_date->toDateString(),
                'end_date' => $fy->end_date->toDateString(),
                'status' => $fy->status->value,
            ])
            ->values()
            ->all();

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
