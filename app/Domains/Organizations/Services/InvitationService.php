<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Organizations\Notifications\InvitationNotification;
use App\Domains\Users\Models\User;
use App\Support\FeatureFlag;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles organization membership invitations: creating, sending,
 * accepting, and revoking invite tokens.
 */
class InvitationService
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Invite Lifecycle
    // ──────────────────────────────────────────────────────────────

    public function invite(Organization $organization, string $email, Role $role, User $inviter): OrganizationInvitation
    {
        // Check if user is already a member
        if ($organization->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => [__('app.user_already_member')],
            ]);
        }

        // Check plan limits in SaaS mode
        if (! $this->canAddMember($organization)) {
            throw ValidationException::withMessages([
                'email' => [__('app.max_users_reached')],
            ]);
        }

        // Cancel any existing pending invitation for this email
        $organization->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $plainToken = Str::random(64);

        $invitation = $organization->invitations()->create([
            'email' => $email,
            'role' => $role->value,
            'token' => hash('sha256', $plainToken),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Store the plain token temporarily so the notification can include it
        $invitation->plain_token = $plainToken;
        $invitation->load('organization');

        Notification::route('mail', $email)
            ->notify((new InvitationNotification($invitation, $plainToken))->locale($organization->locale));

        return $invitation;
    }

    public function accept(string $token): Organization
    {
        $invitation = OrganizationInvitation::where('token', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->firstOrFail();

        // Defense-in-depth: verify the authenticated user's email matches the invitation
        if (auth()->check() && auth()->user()->email !== $invitation->email) {
            abort(403, __('app.invitation_wrong_account'));
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => [__('app.invitation_expired')],
            ]);
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => [__('app.invitation_no_account')],
            ]);
        }

        // Check if already a member (e.g. joined via another path)
        if ($invitation->organization->users()->where('users.id', $user->id)->exists()) {
            $invitation->update(['accepted_at' => now()]);

            return $invitation->organization;
        }

        $this->organizationService->addMember(
            $invitation->organization,
            $user,
            $invitation->role,
        );

        $invitation->update(['accepted_at' => now()]);

        return $invitation->organization;
    }

    // ──────────────────────────────────────────────────────────────
    //  Management
    // ──────────────────────────────────────────────────────────────

    public function cancel(OrganizationInvitation $invitation): void
    {
        $invitation->delete();
    }

    public function resend(OrganizationInvitation $invitation): void
    {
        $invitation->update([
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load('organization');

        Notification::route('mail', $invitation->email)
            ->notify((new InvitationNotification($invitation))->locale($invitation->organization->locale));
    }

    // ──────────────────────────────────────────────────────────────
    //  Capacity Checks
    // ──────────────────────────────────────────────────────────────

    public function canAddMember(Organization $organization): bool
    {
        if (! FeatureFlag::isSaas()) {
            return true;
        }

        $subscription = $organization->activeSubscription ?? null;
        if (! $subscription) {
            return false;
        }

        $maxUsers = $subscription->plan->max_users ?? -1;
        if ($maxUsers === -1) {
            return true;
        }

        $currentCount = $organization->users()->count();
        $pendingCount = $organization->invitations()->pending()->count();

        return ($currentCount + $pendingCount) < $maxUsers;
    }
}
