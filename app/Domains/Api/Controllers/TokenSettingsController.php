<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Api\Requests\StoreOrganizationTokenSettingsRequest;
use App\Domains\Api\Requests\StorePersonalTokenSettingsRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings page for personal and organization API tokens.
 */
class TokenSettingsController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $orgId = $currentOrg->id();

        $personalTokens = $request->user()
            ->tokens()
            ->personal()
            ->where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        $orgTokens = PersonalAccessToken::query()
            ->organization()
            ->where('organization_id', $orgId)
            ->with('tokenable:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
                'created_by' => $token->tokenable->name,
            ]);

        $canManageOrgTokens = $request->user()->can('manageUsers', $organization);

        return Inertia::render('Settings/ApiTokens', [
            'personalTokens' => $personalTokens,
            'orgTokens' => $orgTokens,
            'canManageOrgTokens' => $canManageOrgTokens,
            'abilities' => [
                'customers:read', 'customers:write',
                'invoices:read', 'invoices:write',
                'expenses:read', 'expenses:write',
                'accounts:read',
                'bank-accounts:read',
                'webhooks:read', 'webhooks:write',
            ],
        ]);
    }

    public function storePersonal(StorePersonalTokenSettingsRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validated();

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);
        $token->accessToken->update([
            'organization_id' => $currentOrg->id(),
            'type' => TokenType::Personal,
        ]);

        return redirect()->route('settings.api-tokens')
            ->with('success', __('app.token_created'))
            ->with('newToken', $token->plainTextToken);
    }

    public function storeOrganization(StoreOrganizationTokenSettingsRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('manageUsers', $organization);

        $validated = $request->validated();

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);
        $token->accessToken->update([
            'organization_id' => $currentOrg->id(),
            'type' => TokenType::Organization,
        ]);

        return redirect()->route('settings.api-tokens')
            ->with('success', __('app.token_created'))
            ->with('newToken', $token->plainTextToken);
    }

    public function destroyPersonal(Request $request, int $tokenId, CurrentOrganization $currentOrg): RedirectResponse
    {
        $token = $request->user()
            ->tokens()
            ->personal()
            ->where('id', $tokenId)
            ->where('organization_id', $currentOrg->id())
            ->firstOrFail();

        $token->delete();

        return redirect()->route('settings.api-tokens')
            ->with('success', __('app.token_deleted'));
    }

    public function destroyOrganization(int $tokenId, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('manageUsers', $organization);

        $token = PersonalAccessToken::query()
            ->organization()
            ->where('id', $tokenId)
            ->where('organization_id', $currentOrg->id())
            ->firstOrFail();

        $token->delete();

        return redirect()->route('settings.api-tokens')
            ->with('success', __('app.token_deleted'));
    }
}
