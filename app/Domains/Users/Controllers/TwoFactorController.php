<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Notifications\TwoFactorDisabledNotification;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor authentication setup: enable, confirm, disable, and show recovery codes.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
    ) {}

    public function enable(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $svg = $this->generateQrCodeSvg($qrCodeUrl);

        return redirect()->route('profile')
            ->with('twoFactor', [
                'qrSvg' => $svg,
                'secret' => $secret,
            ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return back()->with('error', trans('app.two_factor_not_started'));
        }

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => trans('app.invalid_two_factor_code')]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        DB::transaction(function () use ($user, $recoveryCodes) {
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => $recoveryCodes->all(),
            ])->save();
        });

        return redirect()->route('profile')
            ->with('twoFactor', [
                'recoveryCodes' => $recoveryCodes->all(),
                'confirmed' => true,
            ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->user()->notify(new TwoFactorDisabledNotification);

        return redirect()->route('profile')
            ->with('success', trans('app.two_factor_disabled'));
    }

    public function showRecoveryCodes(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back()->with('error', trans('app.two_factor_not_enabled'));
        }

        return redirect()->route('profile')
            ->with('twoFactor', [
                'recoveryCodes' => $user->two_factor_recovery_codes,
            ]);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back()->with('error', trans('app.two_factor_not_enabled'));
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        DB::transaction(function () use ($user, $recoveryCodes) {
            $user->forceFill([
                'two_factor_recovery_codes' => $recoveryCodes->all(),
            ])->save();
        });

        return redirect()->route('profile')
            ->with('twoFactor', [
                'recoveryCodes' => $recoveryCodes->all(),
                'regenerated' => true,
            ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function generateRecoveryCodes(): Collection
    {
        return Collection::times(8, fn () => Str::random(16));
    }

    private function generateQrCodeSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }
}
