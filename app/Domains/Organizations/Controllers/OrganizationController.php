<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $organizations = $request->user()->organizations()->get();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function show(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization->load('users'),
        ]);
    }

    public function store(Request $request, OrganizationService $organizationService): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => 'string|in:en,fr,de,it,rm',
        ]);

        $org = $organizationService->create($request->user(), $validated);

        return redirect()->route('organizations.show', $org)
            ->with('success', 'Organization created.');
    }

    public function update(Request $request, Organization $organization, OrganizationService $organizationService): RedirectResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => 'string|in:en,fr,de,it,rm',
        ]);

        $organizationService->update($organization, $validated);

        return redirect()->route('organizations.show', $organization)
            ->with('success', 'Organization updated.');
    }

    public function switchOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $request->user()->switchOrganization($organization);

        return redirect()->route('dashboard')
            ->with('success', "Switched to {$organization->name}.");
    }
}
