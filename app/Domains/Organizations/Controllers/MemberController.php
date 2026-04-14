<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Organization member management: listing, role changes, and removal.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    public function updateRole(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageUsers', $organization);

        abort_unless(
            $organization->users()->where('users.id', $user->id)->exists(),
            404,
        );

        $validated = $request->validate([
            'role' => ['required', new Enum(Role::class)],
        ]);

        $role = Role::from($validated['role']);

        // Only owners can assign the owner role
        if ($role === Role::Owner) {
            $currentUserRole = $organization->users()
                ->where('users.id', $request->user()->id)
                ->first()?->pivot?->role;

            if ($currentUserRole !== 'owner') {
                abort(403, __('app.only_owners_can_assign_owner'));
            }
        }

        $this->organizationService->changeMemberRole($organization, $user, $role);

        return redirect()->route('organizations.show', $organization)
            ->with('success', __('app.role_updated'));
    }

    public function remove(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageUsers', $organization);

        abort_unless(
            $organization->users()->where('users.id', $user->id)->exists(),
            404,
        );

        // Prevent removing self via this endpoint (use leave instead)
        if ($request->user()->id === $user->id) {
            abort(403, __('app.cannot_remove_self'));
        }

        if ($this->organizationService->isLastOwner($organization, $user)) {
            return redirect()->route('organizations.show', $organization)
                ->with('error', __('app.cannot_remove_last_owner'));
        }

        $this->organizationService->removeMember($organization, $user);

        return redirect()->route('organizations.show', $organization)
            ->with('success', __('app.member_removed'));
    }

    public function leave(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        // Verify user is a member
        if (! $organization->users()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        if ($this->organizationService->isLastOwner($organization, $user)) {
            return redirect()->route('organizations.show', $organization)
                ->with('error', __('app.cannot_leave_as_last_owner'));
        }

        // Prevent leaving if sole member
        if ($organization->users()->count() === 1) {
            return redirect()->route('organizations.show', $organization)
                ->with('error', __('app.cannot_leave_as_sole_member'));
        }

        $this->organizationService->removeMember($organization, $user);

        return redirect()->route('organizations.index')
            ->with('success', __('app.left_organization', ['name' => $organization->name]));
    }
}
