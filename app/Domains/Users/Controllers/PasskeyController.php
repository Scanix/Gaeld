<?php

namespace App\Domains\Users\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Support\WebAuthn;

/**
 * WebAuthn passkey registration and removal.
 */
class PasskeyController extends Controller
{
    public function registerOptions(Request $request, GenerateRegistrationOptions $options): JsonResponse
    {
        $registrationOptions = $options($request->user());
        $request->session()->put('passkey.registration_options', WebAuthn::toJson($registrationOptions));

        return response()->json(json_decode(WebAuthn::toJson($registrationOptions), true, flags: JSON_THROW_ON_ERROR));
    }

    public function register(PasskeyRegistrationRequest $request, StorePasskey $store): JsonResponse
    {
        $passkey = $store(
            $request->user(),
            $request->string('name')->toString(),
            $request->credential(),
            $request->registrationOptions(),
        );

        return response()->json([
            'id' => $passkey->getKey(),
            'message' => trans('app.passkey_registered'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $credentials = $request->user()
            ->passkeys()
            ->select(['id', 'name', 'created_at', 'last_used_at'])
            ->get()
            ->map(fn ($cred) => [
                'id' => $cred->id,
                'name' => $cred->name,
                'created_at' => $cred->created_at->toDateString(),
                'last_used' => $cred->last_used_at?->toDateString(),
            ]);

        return response()->json($credentials);
    }

    public function destroy(Request $request, string $credentialId): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $request->user()
            ->passkeys()
            ->where('id', $credentialId)
            ->delete();

        return redirect()->route('profile')
            ->with('success', trans('app.passkey_deleted'));
    }
}
