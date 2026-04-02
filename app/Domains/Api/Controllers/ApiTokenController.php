<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Requests\StoreApiTokenRequest;
use App\Domains\Api\Resources\ApiTokenResource;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Authentication
 *
 * Manage personal API tokens. Tokens are scoped to the authenticated user and current organisation.
 */
class ApiTokenController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrg,
    ) {}

    /**
     * List personal tokens
     *
     * Returns all personal API tokens for the authenticated user in the current organisation.
     *
     * @response 200 scenario="Success" {"data":[{"id":1,"name":"CI Token","abilities":["invoices:read"],"last_used_at":"2025-03-01T12:00:00.000000Z","expires_at":"2025-12-31T23:59:59.000000Z","created_at":"2025-01-15T10:00:00.000000Z"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->tokens()
            ->personal()
            ->where('organization_id', $this->currentOrg->id())
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return ApiTokenResource::collection($tokens);
    }

    /**
     * Create a personal token
     *
     * Creates a new personal API token. The plain-text token is returned only once in the response.
     *
     * @bodyParam name string required A descriptive name for the token. Example: CI Token
     * @bodyParam abilities string[] List of token abilities (permissions). Use `*` for all. Example: ["invoices:read","customers:read"]
     * @bodyParam expires_in_days integer Number of days until the token expires (1-365). Example: 90
     *
     * @response 201 scenario="Created" {"token":"1|abc123def456...","name":"CI Token","type":"personal","abilities":["invoices:read","customers:read"],"expires_at":"2025-04-15T10:00:00.000000Z"}
     * @response 422 scenario="Validation error" {"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     */
    public function store(StoreApiTokenRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $validated = $request->validated();

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $expiresAt,
        );

        $token->accessToken->update([
            'organization_id' => $currentOrg->id(),
            'type' => TokenType::Personal,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $token->accessToken->name,
            'type' => TokenType::Personal->value,
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Revoke a personal token
     *
     * Permanently deletes a personal API token.
     *
     * @urlParam tokenId integer required The token ID. Example: 1
     *
     * @response 204 scenario="Revoked"
     * @response 404 scenario="Not found" {"message":"Token not found."}
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()
            ->tokens()
            ->personal()
            ->where('id', $tokenId)
            ->where('organization_id', $this->currentOrg->id())
            ->firstOrFail();

        $token->delete();

        return response()->json(null, 204);
    }

    /**
     * List available abilities
     *
     * Returns all possible token abilities (permissions) that can be assigned to a token.
     *
     * @response 200 scenario="Success" {"data":["customers:read","customers:write","invoices:read","invoices:write","expenses:read","expenses:write","accounts:read","bank-accounts:read","webhooks:read","webhooks:write"]}
     */
    public function abilities(): JsonResponse
    {
        return response()->json([
            'data' => [
                'customers:read',
                'customers:write',
                'invoices:read',
                'invoices:write',
                'expenses:read',
                'expenses:write',
                'accounts:read',
                'bank-accounts:read',
                'webhooks:read',
                'webhooks:write',
            ],
        ]);
    }

    /**
     * List webhook events
     *
     * Returns all possible webhook event types that can be subscribed to.
     *
     * @response 200 scenario="Success" {"data":["invoice.created","invoice.updated","invoice.deleted","invoice.paid","customer.created","customer.updated","customer.deleted","expense.created","expense.updated","expense.deleted","payment.received","payment.deleted"]}
     */
    public function webhookEvents(): JsonResponse
    {
        return response()->json([
            'data' => WebhookEvent::all(),
        ]);
    }
}
