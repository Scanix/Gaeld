<?php

namespace App\Support\Listeners;

use App\Domains\Users\Models\DeviceSession;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Logs authentication events (login, logout, failed attempts) via Spatie activity log.
 */
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

        // Record device session
        /** @var User $user */
        $user = $event->user;
        $ua = request()->userAgent() ?? '';
        $parsed = DeviceSession::parseUserAgent($ua);

        DeviceSession::updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => session()->getId(),
            ],
            [
                'ip_address' => request()->ip(),
                'user_agent' => $ua,
                'device_name' => $parsed['device_name'],
                'is_desktop' => $parsed['is_desktop'],
                'is_mobile' => $parsed['is_mobile'],
                'platform' => $parsed['platform'],
                'browser' => $parsed['browser'],
                'last_active_at' => now(),
            ],
        );
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

        // Remove device session record
        /** @var User $logoutUser */
        $logoutUser = $event->user;
        DeviceSession::where('user_id', $logoutUser->id)
            ->where('session_id', session()->getId())
            ->delete();
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

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
