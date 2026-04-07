<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Requests\StoreOrganizationRequest;
use App\Domains\Organizations\Services\InvitationService;
use App\Domains\Organizations\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreOrganizationRequest $request, OrganizationService $organizationService): RedirectResponse
    {
        $org = $organizationService->create($request->user(), CreateOrganizationData::fromArray($request->validated()));

        return redirect()->route('organizations.show', $org)
            ->with('success', __('app.organization_created'));
    }

    public function switchOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('view', $organization);

        $request->user()->switchOrganization($organization);

        return redirect()->route('dashboard')
            ->with('success', __('app.organization_switched', ['name' => $organization->name]));
    }
}
