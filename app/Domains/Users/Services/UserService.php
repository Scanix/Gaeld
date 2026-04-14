<?php

namespace App\Domains\Users\Services;

use App\Domains\Accounting\Models\JournalEntry;
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
     *
     * Exception: when the user is the sole owner of an organization that has
     * posted journal entries, Swiss OR Art. 958f requires those financial
     * records to be retained for 10 years. In that case the user's PII is
     * anonymised and all access credentials are revoked, but the org and its
     * ledger entries are preserved.
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Delete activity logs where user is the causer
            Activity::where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->delete();

            // Track whether the user record itself should be hard-deleted.
            // Set to false when financial retention rules prevent full deletion.
            $shouldDeleteUser = true;

            // Delete organizations where the user is the sole member
            foreach ($user->organizations as $org) {
                $memberCount = $org->users()->count();
                if ($memberCount <= 1) {
                    // Sole owner: check for posted accounting records before hard-deleting.
                    // Swiss OR Art. 958f mandates 10-year retention of posted ledger entries.
                    $hasPostedEntries = JournalEntry::withoutGlobalScopes()
                        ->where('organization_id', $org->id)
                        ->where('is_posted', true)
                        ->exists();

                    if ($hasPostedEntries) {
                        // Anonymize the user's PII so they cannot be identified.
                        // Financial records in the org are preserved for audit purposes.
                        $user->name = 'Compte supprimé';
                        $user->email = 'deleted-'.$user->id.'@deleted.invalid';
                        $user->password = '';
                        $user->two_factor_secret = null;
                        $user->two_factor_recovery_codes = null;
                        $user->two_factor_confirmed_at = null;
                        $user->save();

                        // Revoke all API tokens so the account is fully inaccessible
                        $user->tokens()->delete();

                        // Detach from org rather than cascade-deleting it
                        $org->users()->detach($user->id);

                        $shouldDeleteUser = false;
                    } else {
                        // No posted entries: safe to cascade-delete the entire organization
                        $org->delete();
                    }
                } else {
                    // Other members remain: just detach this user
                    $org->users()->detach($user->id);
                }
            }

            // Delete WebAuthn credentials (passkeys) — always, even on anonymization
            $user->webAuthnCredentials()->delete();

            // Delete sessions — always
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($shouldDeleteUser) {
                $user->delete();
            }
        });
    }
}
