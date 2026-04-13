<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\DTOs\UpdateUserProfileData;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * User account management: creation, profile updates, and password changes.
 */
class UserService
{
    // ──────────────────────────────────────────────────────────────
    //  Account Management
    // ──────────────────────────────────────────────────────────────

    public function create(CreateUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'locale' => $data->locale,
            'email_verified_at' => $data->emailVerifiedAt,
            'accepted_privacy_at' => $data->acceptedPrivacyAt,
            'accepted_terms_at' => $data->acceptedTermsAt,
        ]);
    }

    public function updateProfile(User $user, UpdateUserProfileData $data): User
    {
        $user->update([
            'name' => $data->name,
            'locale' => $data->locale,
        ]);

        return $user;
    }

    public function toggleHelp(User $user): bool
    {
        $user->update(['show_help' => ! $user->show_help]);

        return $user->show_help;
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        Log::info('Password changed', ['user_id' => $user->id]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Account Deletion
    // ──────────────────────────────────────────────────────────────

    /**
     * Permanently delete a user account and all associated data (GDPR Art. 17).
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Delete activity logs where user is the causer
            Activity::where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->delete();

            // Delete organizations where the user is the sole member
            foreach ($user->organizations as $org) {
                $memberCount = $org->users()->count();
                if ($memberCount <= 1) {
                    // Sole owner: cascade delete the entire organization
                    $org->delete();
                } else {
                    // Other members remain: just detach this user
                    $org->users()->detach($user->id);
                }
            }

            // Delete WebAuthn credentials (passkeys)
            $user->webAuthnCredentials()->delete();

            // Delete sessions
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Delete the user
            $user->delete();
        });
    }
}
