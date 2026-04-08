<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\DTOs\UpdateUserProfileData;
use App\Domains\Users\Jobs\ExportUserDataJob;
use App\Domains\Users\Notifications\VerifyNewEmailNotification;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * User profile management, password changes, locale, and data export.
 */
class UserController extends Controller
{
    public function profile(Request $request): Response
    {
        $this->authorize('view', $request->user());

        return Inertia::render('Users/Profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request, UserService $userService): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'locale' => ['required', 'string', Rule::in(config('accounting.supported_locales'))],
        ]);

        $userService->updateProfile($request->user(), UpdateUserProfileData::fromArray($validated));

        return redirect()->route('profile')
            ->with('success', __('app.profile_updated'));
    }

    public function updatePassword(Request $request, UserService $userService): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $userService->updatePassword($request->user(), $validated['password']);

        return redirect()->route('profile')
            ->with('success', __('app.password_updated'));
    }

    public function toggleHelp(Request $request, UserService $userService): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $userService->toggleHelp($request->user());

        return back();
    }

    public function updateDashboardLayout(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|string|in:checklist,action_cards,budget,chart,transactions',
            'widgets.*.visible' => 'required|boolean',
        ]);

        $request->user()->update([
            'dashboard_layout' => $validated,
        ]);

        return back();
    }

    public function dismissOnboarding(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->user()->update([
            'onboarding_completed_at' => now(),
        ]);

        return back();
    }

    public function resetOnboarding(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->user()->update([
            'onboarding_completed_at' => null,
        ]);

        return back()->with('success', __('app.onboarding_reset'));
    }

    /**
     * Queue an export of all personal data for the authenticated user (GDPR Art. 15 / Art. 20).
     */
    public function exportData(Request $request): RedirectResponse
    {
        $this->authorize('view', $request->user());

        ExportUserDataJob::dispatch($request->user());

        return back()->with('success', __('app.export_data_queued'));
    }

    /**
     * Download a previously generated data export via signed URL.
     */
    public function downloadExport(Request $request, string $filename): StreamedResponse
    {
        $path = 'exports/'.$filename;

        abort_unless(Storage::disk('local')->exists($path), 404);

        // Ensure the file belongs to the authenticated user
        abort_unless(str_starts_with($filename, 'user-'.$request->user()->id.'-'), 403);

        return Storage::disk('local')->download($path, 'gaeld-data-export.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Permanently delete the authenticated user's account (GDPR Art. 17).
     */
    public function destroyAccount(Request $request, UserService $userService): RedirectResponse
    {
        $this->authorize('delete', $request->user());

        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $userService->deleteAccount($user);

        return redirect('/login')->with('success', __('app.account_deleted'));
    }

    public function requestEmailChange(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
            'current_password' => 'required|current_password',
        ]);

        $user = $request->user();
        $token = Str::random(64);

        $user->update([
            'pending_email' => $validated['email'],
            'email_change_token' => hash('sha256', $token),
            'email_change_requested_at' => now(),
        ]);

        $user->notify(new VerifyNewEmailNotification($token));

        return back()->with('success', __('app.confirm_email_change'));
    }

    public function confirmEmailChange(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->pending_email || ! $user->email_change_token) {
            return redirect()->route('profile')->with('error', __('app.email_change_invalid'));
        }

        if (! hash_equals($user->email_change_token, hash('sha256', $token))) {
            return redirect()->route('profile')->with('error', __('app.email_change_invalid'));
        }

        // Check expiration (24 hours)
        if ($user->email_change_requested_at?->diffInHours(now()) > 24) {
            $user->update([
                'pending_email' => null,
                'email_change_token' => null,
                'email_change_requested_at' => null,
            ]);

            return redirect()->route('profile')->with('error', __('app.email_change_expired'));
        }

        $user->update([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_requested_at' => null,
        ]);

        return redirect()->route('profile')->with('success', __('app.email_changed'));
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->user()->update([
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_requested_at' => null,
        ]);

        return back()->with('success', __('app.email_change_cancelled'));
    }
}
