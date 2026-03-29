<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Services\InvitationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Invite users to an organization and accept/revoke invitations.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {}

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageUsers', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', new Enum(Role::class)],
        ]);

        $role = Role::from($validated['role']);

        // Only owners can invite as owner
        if ($role === Role::Owner) {
            $currentUserRole = $organization->users()
                ->where('users.id', $request->user()->id)
                ->first()?->pivot?->role;

            if ($currentUserRole !== 'owner') {
                abort(403, __('app.only_owners_can_assign_owner'));
            }
        }

        $this->invitationService->invite(
            $organization,
            $validated['email'],
            $role,
            $request->user(),
        );

        return redirect()->route('organizations.show', $organization)
            ->with('success', __('app.invitation_sent'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        if (! $request->user()) {
            // Store the intended URL and redirect to login
            session()->put('url.intended', url("/invitations/{$token}/accept"));

            return redirect()->route('login');
        }

        $organization = $this->invitationService->accept($token);

        $request->user()->switchOrganization($organization);

        return redirect()->route('dashboard')
            ->with('success', __('app.invitation_accepted', ['name' => $organization->name]));
    }

    public function destroy(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageUsers', $organization);

        // Ensure invitation belongs to this organization
        if ($invitation->organization_id !== $organization->id) {
            abort(404);
        }

        $this->invitationService->cancel($invitation);

        return redirect()->route('organizations.show', $organization)
            ->with('success', __('app.invitation_cancelled'));
    }

    public function resend(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageUsers', $organization);

        if ($invitation->organization_id !== $organization->id) {
            abort(404);
        }

        $this->invitationService->resend($invitation);

        return redirect()->route('organizations.show', $organization)
            ->with('success', __('app.invitation_resent'));
    }
}
