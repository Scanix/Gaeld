<?php

namespace App\Http\Middleware;

use App\Domains\Organizations\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current organization for the authenticated user
 * and binds it to the application container. Aborts 403 if the
 * user does not belong to any organization.
 */
class EnsureHasOrganization
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $org = $request->user()?->resolveCurrentOrganization();

        if (! $org) {
            return redirect()->route('onboarding');
        }

        // Suspended organisations are locked out of the app entirely so
        // operators can pause abusive or non-paying tenants without
        // deleting their data. The SaaS admin keeps access — they need
        // to be able to reactivate the organisation.
        if ($org->isSuspended()) {
            $adminEmail = config('ee.saas_admin_email');
            $userEmail = (string) $request->user()->email;
            $isAdmin = $adminEmail && strcasecmp($userEmail, (string) $adminEmail) === 0;

            if (! $isAdmin) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'This organization is currently suspended.',
                        'reason' => $org->suspended_reason,
                    ], 403);
                }

                abort(403, $org->suspended_reason ?? 'This organization is currently suspended.');
            }
        }

        $this->currentOrganization->set($org);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        return $next($request);
    }
}
