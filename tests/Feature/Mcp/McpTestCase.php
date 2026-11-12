<?php

namespace Tests\Feature\Mcp;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Security\SecurityTestCase;

abstract class McpTestCase extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['features.mcp_access' => true, 'features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
    }

    /** @param array<int, string> $abilities */
    protected function mcpToken(array $abilities = ['invoicing.view', 'expenses.view'], ?User $user = null, ?Organization $organization = null): string
    {
        $token = ($user ?? $this->ownerA)->createToken('MCP test', $abilities, now()->addDay());
        $token->accessToken->update([
            'organization_id' => ($organization ?? $this->orgA)->id,
            'type' => TokenType::Personal,
        ]);

        return $token->plainTextToken;
    }

    /** @param array<string, mixed> $params */
    protected function rpc(string $token, string $method, array $params = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params,
        ]);
    }

    /** @param array<string, mixed> $arguments */
    protected function callTool(string $token, string $name, array $arguments = []): TestResponse
    {
        return $this->rpc($token, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }
}
