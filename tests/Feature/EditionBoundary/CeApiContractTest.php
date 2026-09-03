<?php

namespace Tests\Feature\EditionBoundary;

use Tests\TestCase;

class CeApiContractTest extends TestCase
{
    public function test_ce_api_contract_retains_authentication_and_core_route_metadata(): void
    {
        $contract = json_decode(
            $this->readFile(base_path('contract/api-contract.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('bearer', $contract['authentication']['type']);
        $this->assertContains('auth:sanctum', $contract['authentication']['middleware_stack']);
        $this->assertContains('api-org', $contract['authentication']['middleware_stack']);
        $this->assertContains('feature:api_access', $contract['authentication']['middleware_stack']);

        $routeNames = [];
        foreach ($contract['routes'] as $route) {
            $routeNames[] = $route['name'];
        }

        foreach (['api.info', 'api.customers.index', 'api.invoices.index', 'api.expenses.index'] as $routeName) {
            $this->assertContains($routeName, $routeNames);
        }
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
