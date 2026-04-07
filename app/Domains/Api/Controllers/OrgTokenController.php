<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Api\Requests\StoreOrgTokenRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * @group Authentication
 *
 * Manage organisation-scoped API tokens. Requires the `manageUsers` permission.
 * Organisation tokens are shared across the organisation and not tied to a single user.
 */
class OrgTokenController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CurrentOrganization $currentOrg,
    ) {}

    /**
     * List organisation tokens
     *
     * Returns all organisation-scoped API tokens for the current organisation.
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b","name":"Production Key","abilities":["*"],"last_used_at":"2025-03-15T08:00:00.000000Z","expires_at":null,"created_at":"2025-01-10T10:00:00.000000Z","created_by":"John Doe"}]}
     */
    public function index(): JsonResponse
    {
        $this->authorize('manageUsers', $this->currentOrg->get());

        $tokens = PersonalAccessToken::query()
            ->organization()
            ->where('organization_id', $this->currentOrg->id())
            ->get(['id', 'uuid', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'tokenable_id']);

        // Include the creator name for each token
        $tokens->load('tokenable:id,name');

        $data = $tokens->map(fn ($token) => [
            'id' => $token->uuid,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'created_by' => $token->tokenable?->name,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Create an organisation token
     *
     * Creates a new organisation-scoped API token. The plain-text token is returned only once.
     *
     * @bodyParam name string required A descriptive name for the token. Example: Production Key
     * @bodyParam abilities string[] Token abilities. Use `*` for full access. Example: ["*"]
     * @bodyParam expires_in_days integer Number of days until the token expires (1-365). Example: 365
     *
     * @response 201 scenario="Created" {"token":"2|xyz789...","name":"Production Key","type":"organization","abilities":["*"],"expires_at":null}
     * @response 422 scenario="Validation error" {"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     * @response 403 scenario="Forbidden" {"message":"This action is unauthorized."}
     */
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

    /**
     * Revoke an organisation token
     *
     * Permanently deletes an organisation-scoped API token.
     *
     * @urlParam tokenUuid string required The token UUID. Example: 9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b
     *
     * @response 204 scenario="Revoked"
     * @response 404 scenario="Not found" {"message":"Token not found."}
     * @response 403 scenario="Forbidden" {"message":"This action is unauthorized."}
     */
    public function destroy(string $tokenUuid): JsonResponse
    {
        $this->authorize('manageUsers', $this->currentOrg->get());

        $token = PersonalAccessToken::query()
            ->organization()
            ->where('uuid', $tokenUuid)
            ->where('organization_id', $this->currentOrg->id())
            ->firstOrFail();

        $token->delete();

        return response()->json(null, 204);
    }
}
