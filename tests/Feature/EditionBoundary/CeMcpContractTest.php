<?php

namespace Tests\Feature\EditionBoundary;

use App\Domains\Api\Mcp\GaeldServer;
use ReflectionClass;
use Tests\TestCase;

class CeMcpContractTest extends TestCase
{
    public function test_the_public_mcp_contract_keeps_the_server_in_ce_and_matches_its_tool_catalogue(): void
    {
        $contract = json_decode(file_get_contents(base_path('contract/mcp-contract.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ce', $contract['owner']);
        $this->assertFalse($contract['self_hosted_ce']['requires_commercial_subscription']);
        $this->assertFalse($contract['self_hosted_ce']['requires_private_registry']);
        $this->assertTrue($contract['read_only']);
        $this->assertSame(['team'], $contract['hosted']['plans']);
        $this->assertSame('mcp_access', $contract['hosted']['entitlement']);
        $classes = (new ReflectionClass(GaeldServer::class))->getDefaultProperties()['tools'];
        $names = array_map(fn (string $class): string => (new $class)->name(), $classes);
        $this->assertEqualsCanonicalizing($names, $contract['tools']);
    }

    public function test_mcp_is_a_production_dependency_and_both_installation_flags_protect_the_route(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('laravel/mcp', $composer['require']);
        $middleware = app('router')->getRoutes()->getByName('mcp')->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('feature:mcp_access', $middleware);
        $this->assertContains('feature:api_access', $middleware);
        $this->assertFalse((bool) config('features.mcp_access'));
    }
}
