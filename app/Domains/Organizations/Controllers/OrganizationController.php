<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Requests\StoreOrganizationRequest;
use App\Domains\Organizations\Services\InvitationService;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $organizations = $request->user()->organizations()->get();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('Organizations/Create');
    }

    public function show(Organization $organization, InvitationService $invitationService): Response
    {
        $this->authorize('view', $organization);

        $canManageUsers = request()->user()->can('manageUsers', $organization);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization->load('users'),
            'invitations' => $canManageUsers
                ? $organization->invitations()->pending()->with('inviter:id,name')->get()
                : [],
            'canManageUsers' => $canManageUsers,
            'canAddMember' => $canManageUsers && $invitationService->canAddMember($organization),
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

    public function switchOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('view', $organization);

        $request->user()->switchOrganization($organization);

        return redirect()->route('dashboard')
            ->with('success', __('app.organization_switched', ['name' => $organization->name]));
    }
}
