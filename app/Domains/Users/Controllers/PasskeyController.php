<?php

namespace App\Domains\Users\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

/**
 * WebAuthn passkey registration and removal.
 */
class PasskeyController extends Controller
{
    public function registerOptions(AttestationRequest $request): JsonResponse
    {
        return $request->toCreate();
    }

    public function register(AttestedRequest $request): JsonResponse
    {
        $id = $request->save(
            fn ($credential) => $credential->alias = $request->input('name', 'Passkey'),
        );

        return response()->json([
            'id' => $id,
            'message' => trans('app.passkey_registered'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $credentials = $request->user()
            ->webAuthnCredentials()
            ->select(['id', 'alias', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($cred) => [
                'id' => $cred->id,
                'name' => $cred->alias ?? 'Passkey',
                'created_at' => $cred->created_at->toDateString(),
                'last_used' => $cred->updated_at->toDateString(),
            ]);

        return response()->json($credentials);
    }

    public function destroy(Request $request, string $credentialId): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        $request->user()
            ->webAuthnCredentials()
            ->where('id', $credentialId)
            ->delete();

        return redirect()->route('profile')
            ->with('success', trans('app.passkey_deleted'));
    }
}
