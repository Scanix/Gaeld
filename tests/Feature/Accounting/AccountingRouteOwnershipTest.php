<?php

namespace Tests\Feature\Accounting;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountingRouteOwnershipTest extends TestCase
{
    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function ownedAccountingRoutes(): array
    {
        return [
            ['GET', 'accounting/tax-declarations'],
            ['POST', 'accounting/tax-declarations'],
            ['GET', 'accounting/cost-centers'],
            ['POST', 'accounting/cost-centers'],
            ['GET', 'accounting/analytical-report'],
            ['GET', 'accounting/exchange-rates'],
            ['POST', 'accounting/exchange-rates'],
            ['POST', 'accounting/exchange-rates/fetch-ecb'],
            ['GET', 'accounting/consolidation'],
            ['POST', 'accounting/consolidation/groups'],
        ];
    }

    #[DataProvider('ownedAccountingRoutes')]
    public function test_core_owns_public_accounting_routes(string $method, string $uri): void
    {
        $matchingActions = [];

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== $uri) {
                continue;
            }

            if (! in_array($method, $route->methods(), true)) {
                continue;
            }

            $matchingActions[] = $route->getActionName();
        }

        $this->assertNotEmpty(
            $matchingActions,
            "Expected route not found for {$method} {$uri}"
        );

        foreach ($matchingActions as $action) {
            $this->assertStringStartsWith(
                'App\\Domains\\Accounting\\Controllers\\',
                $action,
                "Route {$method} {$uri} must be owned by core accounting controllers. Found: {$action}"
            );

            $this->assertStringNotContainsString(
                'Plugins\\GaeldEE\\',
                $action,
                "Route {$method} {$uri} must not be handled by EE plugin controllers. Found: {$action}"
            );
        }
    }
}
