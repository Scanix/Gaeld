<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Api\Requests\StoreOrgTokenRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class OrgTokenController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        // Only org owners and admins can manage organization tokens
    }

    public function index(): JsonResponse
    {
        $this->authorize('manageUsers', app(CurrentOrganization::class)->get());

        $orgId = app(CurrentOrganization::class)->id();

        $tokens = PersonalAccessToken::query()
            ->organization()
            ->where('organization_id', $orgId)
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'tokenable_id']);

        // Include the creator name for each token
        $tokens->load('tokenable:id,name');

        $data = $tokens->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'created_by' => $token->tokenable?->name,
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(StoreOrgTokenRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('manageUsers', $currentOrg->get());

        $validated = $request->validated();

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        // Create the token on the current user (as creator), but mark it as an org token
        $token = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $expiresAt,
        );

        $token->accessToken->update([
            'organization_id' => $currentOrg->id(),
            'type' => TokenType::Organization,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $token->accessToken->name,
            'type' => TokenType::Organization->value,
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    public function destroy(int $tokenId): JsonResponse
    {
        $this->authorize('manageUsers', app(CurrentOrganization::class)->get());

        $token = PersonalAccessToken::query()
            ->organization()
            ->where('id', $tokenId)
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->firstOrFail();

        $token->delete();

        return response()->json(null, 204);
    }
}
