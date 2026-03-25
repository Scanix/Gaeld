<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Actions\CreateOrganizationAction;
use App\Domains\Organizations\Actions\UpdateOrganizationAction;
use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\InvitationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(Request $request, CreateOrganizationAction $action): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => 'string|in:en,fr,de,it,rm',
        ]);

        $org = $action->execute($request->user(), CreateOrganizationData::fromArray($validated));

        return redirect()->route('organizations.show', $org)
            ->with('success', 'Organization created.');
    }

    public function update(Request $request, Organization $organization, UpdateOrganizationAction $action): RedirectResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => 'string|in:en,fr,de,it,rm',
            'require_two_factor' => 'sometimes|boolean',
            'default_payment_terms_days' => 'sometimes|integer|min:0|max:365',
        ]);

        $action->execute($organization, UpdateOrganizationData::fromArray($validated));

        return redirect()->route('organizations.show', $organization)
            ->with('success', 'Organization updated.');
    }

    public function switchOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('view', $organization);

        $request->user()->switchOrganization($organization);

        return redirect()->route('dashboard')
            ->with('success', "Switched to {$organization->name}.");
    }
}
