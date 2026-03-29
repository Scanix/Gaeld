<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Actions\CreateUserAction;
use App\Domains\Users\DTOs\CreateUserData;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User self-registration.
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, CreateUserAction $action): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if (FeatureFlag::isSaas()) {
            $rules['accepted_privacy'] = ['required', 'accepted'];
        }

        $validated = $request->validate($rules);

        if (FeatureFlag::isSaas()) {
            $validated['accepted_privacy_at'] = now();
            $validated['accepted_terms_at'] = now();
        }

        $user = $action->execute(CreateUserData::fromArray($validated));

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
