<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            Log::channel('stack')->warning('Failed login attempt', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // If user has 2FA enabled, redirect to challenge instead of completing login
        if ($user->hasTwoFactorEnabled()) {
            $userId = $user->id;

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put('two_factor:user_id', $userId);
            $request->session()->put('two_factor:remember', $remember);

            return redirect()->route('two-factor.create');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::channel('stack')->info('User logged in', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
