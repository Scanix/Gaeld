<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\Services\UserService;
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
    public function create(): Response|RedirectResponse
    {
        // In SaaS mode, registration must go through /signup (with plan + subscription)
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, UserService $userService): RedirectResponse
    {
        // In SaaS mode, prevent direct registration without a subscription
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        $validated = $request->validate($rules);
        $validated['locale'] = app()->getLocale();

        $user = $userService->create(CreateUserData::fromArray($validated));

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
