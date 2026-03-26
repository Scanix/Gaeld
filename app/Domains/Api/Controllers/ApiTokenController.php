<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->personal()
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

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
            ->where('organization_id', app(CurrentOrganization::class)->id())
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
