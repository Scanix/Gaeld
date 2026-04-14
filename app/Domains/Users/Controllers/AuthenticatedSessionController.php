<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Models\User;
use App\Domains\Users\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Cookie;

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
                'email' => __('app.invalid_credentials'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // If user has any 2FA method (TOTP or passkey), redirect to challenge
        if ($user->hasAnyTwoFactor()) {
            $userId = $user->id;

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put('two_factor:user_id', $userId);
            $request->session()->put('two_factor:remember', $remember);

            return redirect()->route('two-factor.create');
        }

        $request->session()->regenerate();

        Log::channel('stack')->info('User logged in', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'))
            ->withCookie($this->authCookie(true));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withCookie($this->authCookie(false));
    }

    /**
     * Create a cross-domain cookie to signal login state to the landing site.
     * Read server-side by the Next.js edge middleware — httpOnly is safe.
     */
    private function authCookie(bool $authenticated): Cookie
    {
        $domain = config('session.domain') ?: null;

        return cookie(
            'gaeld_auth',
            $authenticated ? '1' : '',
            $authenticated ? config('session.lifetime') : -1,
            '/',
            $domain,
            config('session.secure', true),
            true,  // httpOnly — Next.js middleware reads cookies server-side, not via JS
            false,
            config('session.same_site', 'lax'),
        );
    }
}
