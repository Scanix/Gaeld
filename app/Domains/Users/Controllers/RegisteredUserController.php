<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Actions\CreateUserAction;
use App\Domains\Users\DTOs\CreateUserData;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, CreateUserAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $action->execute(CreateUserData::fromArray($validated));

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
