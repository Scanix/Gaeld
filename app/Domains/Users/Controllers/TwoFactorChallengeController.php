<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Models\User;
use App\Domains\Users\Requests\TwoFactorChallengeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Handles the two-factor authentication challenge during login.
 *
 * Supports three methods: TOTP code, recovery code, and passkey (WebAuthn).
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('two_factor:user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'availableMethods' => $this->getAvailableMethods($user),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor:user_id');
        $remember = $request->session()->get('two_factor:remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::findOrFail($userId);

        if ($request->filled('code')) {
            $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

            if (! $valid) {
                throw ValidationException::withMessages([
                    'code' => trans('app.invalid_two_factor_code'),
                ]);
            }
        } elseif ($request->filled('recovery_code')) {
            $valid = $this->validateRecoveryCode($user, $request->recovery_code);

            if (! $valid) {
                throw ValidationException::withMessages([
                    'recovery_code' => trans('app.invalid_recovery_code'),
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'code' => trans('app.two_factor_code_required'),
            ]);
        }

        Auth::login($user, $remember);

        $request->session()->forget(['two_factor:user_id', 'two_factor:remember']);
        $request->session()->put('two_factor_authenticated', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->withCookie($this->authCookie());
    }

    /**
     * Return WebAuthn assertion options so the user can verify via passkey as 2FA.
     */
    public function passkeyOptions(Request $request, GenerateVerificationOptions $options): JsonResponse
    {
        $userId = $request->session()->get('two_factor:user_id');

        if (! $userId) {
            return response()->json(['message' => 'No pending 2FA challenge.'], 403);
        }

        /** @var User $user */
        $user = User::findOrFail($userId);

        $credentials = $user->passkeys()->get();

        if ($credentials->isEmpty()) {
            return response()->json(['message' => 'No passkeys registered.'], 422);
        }

        $verificationOptions = $options($user);
        $request->session()->put('passkey.verification_options', WebAuthn::toJson($verificationOptions));

        return response()->json(json_decode(WebAuthn::toJson($verificationOptions), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Verify passkey assertion as 2FA.
     */
    public function passkeyVerify(PasskeyVerificationRequest $request, VerifyPasskey $verify): RedirectResponse|JsonResponse
    {
        $userId = $request->session()->get('two_factor:user_id');
        $remember = $request->session()->get('two_factor:remember', false);

        if (! $userId) {
            return response()->json(['message' => 'No pending 2FA challenge.'], 403);
        }

        /** @var User $user */
        $user = User::findOrFail($userId);

        $passkey = $verify($request->credential(), $request->verificationOptions(), $user);

        // Challenge valid — authenticate
        Auth::login($user, $remember);

        $request->session()->forget(['two_factor:user_id', 'two_factor:remember', 'passkey.verification_options']);
        $request->session()->put('two_factor_authenticated', true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => route('dashboard'),
        ])->withCookie($this->authCookie());
    }

    private function authCookie(): Cookie
    {
        $domain = config('session.domain') ?: null;

        return cookie(
            'gaeld_auth',
            '1',
            config('session.lifetime'),
            '/',
            $domain,
            config('session.secure', true),
            true,  // httpOnly — Next.js middleware reads cookies server-side, not via JS
            false,
            config('session.same_site', 'lax'),
        );
    }

    /** @return list<string> */
    private function getAvailableMethods(User $user): array
    {
        $methods = [];

        if ($user->hasTwoFactorEnabled()) {
            $methods[] = 'totp';
            $methods[] = 'recovery';
        }

        if ($user->hasPasskeysEnabled()) {
            $methods[] = 'passkey';
        }

        return $methods;
    }

    private function validateRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $codes = $user->two_factor_recovery_codes ?? [];

            $index = null;
            foreach ($codes as $i => $storedCode) {
                if (hash_equals($storedCode, $code)) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                return false;
            }

            unset($codes[$index]);

            $user->forceFill([
                'two_factor_recovery_codes' => array_values($codes),
            ])->save();

            return true;
        });
    }
}
