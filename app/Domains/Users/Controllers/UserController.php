<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\DTOs\UpdateUserProfileData;
use App\Domains\Users\Services\DataExportService;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

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
            'locale' => 'required|string|in:en,fr,de,it,rm',
        ]);

        $userService->updateProfile($request->user(), UpdateUserProfileData::fromArray($validated));

        return redirect()->route('profile')
            ->with('success', 'Profile updated.');
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
            ->with('success', 'Password updated.');
    }

    public function toggleHelp(Request $request, UserService $userService): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $userService->toggleHelp($request->user());

        return back();
    }

    /**
     * Export all personal data for the authenticated user (GDPR Art. 15 / Art. 20).
     */
    public function exportData(Request $request, DataExportService $exportService): JsonResponse
    {
        $this->authorize('view', $request->user());

        $data = $exportService->export($request->user());

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="gaeld-data-export.json"',
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

        return redirect('/login')->with('success', 'Your account has been permanently deleted.');
    }
}
