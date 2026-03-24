<?php

namespace App\Support\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class AuthAuditSubscriber
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('logged in');
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->log('logged out');
    }

    public function handleFailed(Failed $event): void
    {
        activity('auth')
            ->withProperties([
                'email' => $event->credentials['email'] ?? 'unknown',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('login failed');
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
