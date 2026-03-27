<?php

namespace App\Http\Middleware;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use App\Http\Middleware\Api\TokenPermissionMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiOrganization
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token || ! $token->organization_id) {
            return response()->json(['message' => 'Token is not associated with an organization.'], 403);
        }

        $org = $this->resolveOrganization($request, $token);

        if (! $org) {
            return response()->json(['message' => 'Organization not found or access denied.'], 403);
        }

        $this->currentOrganization->set($org);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        // Register a Gate::before callback so that token abilities are enforced.
        // For organization tokens, this also bypasses policies' org-membership checks.
        $this->registerTokenAbilityGate($token->type);

        return $next($request);
    }

    private function resolveOrganization(Request $request, $token): ?Organization
    {
        if ($token->type === TokenType::Organization) {
            return Organization::find($token->organization_id);
        }

        return $request->user()
            ->organizations()
            ->where('organizations.id', $token->organization_id)
            ->first();
    }

    /**
     * For organization tokens, bypass the policy entirely and let
     * hasPermissionTo (which checks token abilities) be the sole gate.
     * For personal tokens, additionally check token abilities.
     * Data isolation is enforced by the BelongsToOrganization scope.
     */
    private function registerTokenAbilityGate(TokenType $tokenType): void
    {
        $permissionMap = TokenPermissionMap::get();

        Gate::before(function (User $user, string $ability, array $arguments) use ($permissionMap, $tokenType) {
            $token = $user->currentAccessToken();

            if (! $token instanceof PersonalAccessToken) {
                return null;
            }

            // Wildcard token — allow all actions
            if (in_array('*', $token->abilities)) {
                return $tokenType === TokenType::Organization ? true : null;
            }

            // Resolve the required Spatie permission from the gate context
            $model = $arguments[0] ?? null;
            $modelClass = is_object($model) ? get_class($model) : $model;
            $permission = $permissionMap[$modelClass][$ability] ?? null;

            if ($permission === null) {
                return null; // unmapped — let the policy decide
            }

            if (! $token->can($permission->value)) {
                return false;
            }

            // For org tokens, grant access directly (bypass policy)
            // For personal tokens, return null to let the policy also check
            return $tokenType === TokenType::Organization ? true : null;
        });
    }
}
