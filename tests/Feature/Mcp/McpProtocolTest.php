<?php

namespace Tests\Feature\Mcp;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

class McpProtocolTest extends McpTestCase
{
    /** @return array<int, array{string}> */
    public static function legacyVersions(): array
    {
        return [['2024-11-05'], ['2025-03-26'], ['2025-06-18'], ['2025-11-25']];
    }

    #[DataProvider('legacyVersions')]
    public function test_clients_can_initialize_and_use_their_scoped_tools(string $version): void
    {
        $token = $this->mcpToken(['invoicing.view']);
        $this->rpc($token, 'initialize', [
            'protocolVersion' => $version,
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'Gäld test', 'version' => '1.0'],
        ])->assertOk()->assertJsonPath('result.protocolVersion', $version)
            ->assertJsonPath('result.serverInfo.name', 'Gäld')->assertJsonMissingPath('result.resultType');

        $this->withHeader('MCP-Protocol-Version', $version);
        $tools = $this->rpc($token, 'tools/list')->assertOk()->json('result.tools');
        $this->assertEqualsCanonicalizing(['list_invoices', 'get_invoice'], array_column($tools, 'name'));
        $this->callTool($token, 'list_invoices')->assertOk()
            ->assertJsonPath('result.structuredContent.organization.id', $this->orgA->id);
    }

    public function test_modern_discovery_and_tool_calls_follow_the_public_contract(): void
    {
        $token = $this->mcpToken(['invoicing.view', 'expenses.view', 'reporting.view', 'accounting.view']);
        $discovery = $this->modernRequest($token, 'server/discover')->assertOk()
            ->assertJsonPath('result.resultType', 'complete');
        $this->assertContains('2026-07-28', $discovery->json('result.supportedVersions'));
        $tools = $this->modernRequest($token, 'tools/list')->assertOk()->json('result.tools');
        $contract = json_decode(file_get_contents(base_path('contract/mcp-contract.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing($contract['tools'], array_column($tools, 'name'));
        $this->withHeader('Mcp-Name', 'list_invoices');
        $this->modernRequest($token, 'tools/call', ['name' => 'list_invoices', 'arguments' => (object) []])
            ->assertOk()->assertJsonPath('result.structuredContent.organization.id', $this->orgA->id);
    }

    public function test_modern_requests_still_require_the_matching_method_header(): void
    {
        $this->withHeader('MCP-Protocol-Version', '2026-07-28');
        $this->rpc($this->mcpToken(), 'tools/list', ['_meta' => $this->modernMetadata()])
            ->assertBadRequest();
    }

    /** @param array<string, mixed> $params */
    private function modernRequest(string $token, string $method, array $params = []): TestResponse
    {
        $this->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method]);

        return $this->rpc($token, $method, [...$params, '_meta' => $this->modernMetadata()]);
    }

    /** @return array<string, mixed> */
    private function modernMetadata(): array
    {
        return [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ];
    }
}
