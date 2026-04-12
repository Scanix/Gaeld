<?php

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Models\DeviceSession;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class DeviceSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $sessions = $user
            ->deviceSessions()
            ->orderByDesc('last_active_at')
            ->get()
            ->map(function (DeviceSession $session) use ($currentSessionId): array {
                return [
                    'id' => $session->id,
                    'device_name' => $session->device_name,
                    'browser' => $session->browser,
                    'platform' => $session->platform,
                    'ip_address' => $session->ip_address,
                    'is_desktop' => $session->is_desktop,
                    'is_mobile' => $session->is_mobile,
                    'is_current' => $session->session_id === $currentSessionId,
                    'last_active_at' => (string) $session->last_active_at,
                ];
            });

        return response()->json($sessions);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        /** @var User $user */
        $user = $request->user();

        $session = $user
            ->deviceSessions()
            ->where('id', $id)
            ->firstOrFail();

        // Invalidate the Redis/database session
        $this->invalidateSession($session->session_id);

        $session->delete();

        return redirect()->route('profile')
            ->with('success', __('app.session_revoked'));
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
        ]);

        /** @var User $user */
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $otherSessions = $user
            ->deviceSessions()
            ->where('session_id', '!=', $currentSessionId)
            ->get();

        foreach ($otherSessions as $session) {
            $this->invalidateSession($session->session_id);
            $session->delete();
        }

        return redirect()->route('profile')
            ->with('success', __('app.other_sessions_revoked'));
    }

    private function invalidateSession(string $sessionId): void
    {
        $driver = config('session.driver');

        if ($driver === 'redis') {
            $prefix = config('cache.prefix', 'laravel');
            Redis::del("{$prefix}:{$sessionId}");
        } elseif ($driver === 'database') {
            \DB::table(config('session.table', 'sessions'))
                ->where('id', $sessionId)
                ->delete();
        }
    }
}
