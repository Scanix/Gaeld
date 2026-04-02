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
 * Personal API token management (create, list, revoke).
 */
class ApiTokenController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrg,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->tokens()
            ->personal()
            ->where('organization_id', $this->currentOrg->id())
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return ApiTokenResource::collection($tokens);
    }

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

    public function webhookEvents(): JsonResponse
    {
        return response()->json([
            'data' => WebhookEvent::all(),
        ]);
    }
}
