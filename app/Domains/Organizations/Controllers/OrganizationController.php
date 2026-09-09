<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Requests\StoreOrganizationRequest;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use App\Support\Contracts\OrganizationQuotaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization CRUD and multi-org switching.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Organization::class);

        $user = $request->user();
        $organizations = $user->organizations()->get();
        $organizationLimit = app(OrganizationQuotaResolver::class)->maxOrganizations($user);
        $organizationCount = $organizations->count();
        $currentOrganization = $user->resolveCurrentOrganization();
        $plan = $currentOrganization?->getAttribute('activeSubscription')?->getPlan();
        $ownedOrganizations = $user->organizations()
            ->wherePivot('role', 'owner')
            ->get();
        $hasFreeOrganization = $ownedOrganizations->contains(
            function (Organization $organization): bool {
                $plan = $organization->activeSubscription?->getPlan();

                return (float) data_get($plan, 'price_chf', 0) === 0.0;
            },
        );
        $paidOrganizationCreationAvailable = Route::has('billing.index') && $ownedOrganizations->isNotEmpty();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'canCreateOrganization' => $user->can('create', Organization::class),
            'organizationQuota' => [
                'count' => $organizationCount,
                'limit' => $organizationLimit,
                'plan_name' => is_object($plan) ? ($plan->name ?? null) : null,
                'billing_available' => Route::has('billing.index'),
                'has_free_organization' => $hasFreeOrganization,
                'paid_creation_available' => $paidOrganizationCreationAvailable,
                'requires_paid_plan_for_next' => $hasFreeOrganization && $paidOrganizationCreationAvailable,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('Organizations/Create');
    }

    public function show(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $canManageUsers = request()->user()->can('manageUsers', $organization);
        $members = $organization->users()->count();
        $pendingInvitations = $organization->invitations()->pending()->count();
        $memberLimit = app(OrganizationQuotaResolver::class)->maxUsers($organization);
        $plan = $organization->getAttribute('activeSubscription')?->getPlan();

        return Inertia::render('Organizations/Show', [
            'organization' => $organization->load('users'),
            'invitations' => $canManageUsers
                ? $organization->invitations()->pending()->with('inviter:id,name')->get()
                : [],
            'canManageUsers' => $canManageUsers,
            'canAddMember' => $canManageUsers
                && ($memberLimit === -1 || ($members + $pendingInvitations) < $memberLimit),
            'memberQuota' => [
                'members' => $members,
                'pending_invitations' => $pendingInvitations,
                'total' => $members + $pendingInvitations,
                'limit' => $memberLimit,
                'plan_name' => is_object($plan) ? ($plan->name ?? null) : null,
                'billing_available' => Route::has('billing.index'),
            ],
            'availableEmployees' => $canManageUsers
                ? Employee::query()->whereNull('user_id')->orderBy('last_name')->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'email'])
                    ->map(fn (Employee $employee): array => [
                        'value' => $employee->id,
                        'label' => $employee->fullName(),
                        'email' => $employee->email,
                    ])
                : [],
        ]);
    }

    public function store(
        StoreOrganizationRequest $request,
        OrganizationService $organizationService,
        OrganizationSetupService $setupService,
    ): RedirectResponse {
        $validated = $request->validated();

        $org = DB::transaction(function () use ($request, $validated, $organizationService, $setupService) {
            $org = $organizationService->create($request->user(), CreateOrganizationData::fromArray($validated));

            if (($validated['chart_of_accounts'] ?? 'none') !== 'none') {
                $setupService->seedChartOfAccounts($org, $validated['chart_of_accounts']);
            }

            return $org;
        });
        $request->user()->switchOrganization($org);

        return redirect()->route('organizations.show', $org)
            ->with('success', __('app.organization_created'));
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationService $organizationService,
    ): RedirectResponse {
        $this->authorize('delete', $organization);

        $organizationService->delete($organization);

        // If the deleted org was the active one, clear the session
        if ($request->session()->get('current_organization_id') === $organization->id) {
            $request->session()->forget('current_organization_id');
        }

        return redirect()->route('organizations.index')
            ->with('success', __('app.organization_deleted'));
    }

    /**
     * Resolve the target organization manually rather than via implicit route-model
     * binding: binding runs in the global middleware stack before this route's
     * 'verified' middleware, so a bound-vs-missing model produced a 403 (unverified)
     * vs 404 (not found) differential that let anyone enumerate organization UUIDs.
     * Looking it up here — after 'verified' has already gated the request — keeps
     * unauthenticated/unverified users on a single response regardless of the ID.
     */
    public function switchOrganization(Request $request, string $organization): RedirectResponse
    {
        $model = Str::isUuid($organization) ? Organization::find($organization) : null;
        abort_unless($model && $request->user()->can('view', $model), 404);

        $request->user()->switchOrganization($model);

        // Regenerate session ID when privilege context changes (different org scope)
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', __('app.organization_switched', ['name' => $model->name]));
    }
}
