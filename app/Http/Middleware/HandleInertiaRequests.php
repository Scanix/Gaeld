<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);
        }

        return array_merge(parent::share($request), [
            'auth' => $user ? [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'show_help' => $user->show_help,
                ],
                'currentOrganization' => $user->resolveCurrentOrganization()?->only(
                    'id', 'name', 'currency', 'locale'
                ),
                'organizations' => $user->organizations()
                    ->select('organizations.id', 'organizations.name')
                    ->get()
                    ->map(fn ($org) => [
                        'id' => $org->id,
                        'name' => $org->name,
                        'role' => $org->pivot->role,
                    ]),
            ] : null,
            'locale' => App::getLocale(),
            'translations' => fn () => trans('app'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
