<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Models\User;
use App\Domains\Users\Requests\TwoFactorChallengeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the two-factor authentication challenge during login.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor:user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor:user_id');
        $remember = $request->session()->get('two_factor:remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

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

        return redirect()->intended(route('dashboard'));
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
