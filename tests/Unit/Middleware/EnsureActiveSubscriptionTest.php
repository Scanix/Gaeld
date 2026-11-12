<?php

namespace Tests\Unit\Middleware;

use App\Domains\Organizations\Models\Organization;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Support\Contracts\FeatureResolver;
use App\Support\Contracts\SubscriptionContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureActiveSubscriptionTest extends TestCase
{
    public function test_allows_an_active_subscription(): void
    {
        $response = $this->runMiddleware($this->subscription(isActive: true));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_allows_a_valid_trial(): void
    {
        $response = $this->runMiddleware($this->subscription(status: 'trialing', isTrialing: true));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_blocks_an_expired_trial(): void
    {
        $response = $this->runMiddleware($this->subscription(
            status: 'trialing',
            isTrialing: true,
            isTrialExpired: true,
        ));

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('nonEntitledSubscriptionStatuses')]
    public function test_blocks_non_entitled_subscription_statuses(string $status): void
    {
        $response = $this->runMiddleware($this->subscription(status: $status));

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/billing', parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }

    /** @return array<string, array{string}> */
    public static function nonEntitledSubscriptionStatuses(): array
    {
        return [
            'past due' => ['past_due'],
            'paused' => ['paused'],
            'canceled' => ['canceled'],
        ];
    }

    #[DataProvider('exportRoutes')]
    public function test_export_routes_remain_available_without_an_active_subscription(string $routeName): void
    {
        foreach (['trialing', 'past_due', 'paused', 'canceled'] as $status) {
            $response = $this->runMiddleware($this->subscription(status: $status, isTrialExpired: true), $routeName);

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $status);
        }

        $this->assertSame(Response::HTTP_OK, $this->runMiddleware(null, $routeName)->getStatusCode());
    }

    /** @return array<string, array{string}> */
    public static function exportRoutes(): array
    {
        return [
            'export request' => ['settings.export'],
            'signed download' => ['settings.export.download'],
        ];
    }

    public function test_export_exemption_does_not_open_accounting_or_settings_writes(): void
    {
        foreach (['accounting.chart', 'accounting.journal', 'settings.general', 'settings.export.other'] as $routeName) {
            $response = $this->runMiddleware($this->subscription(status: 'canceled'), $routeName);

            $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode(), $routeName);
            $this->assertSame('/billing', parse_url($response->headers->get('Location'), PHP_URL_PATH));
        }
    }

    private function runMiddleware(?SubscriptionContract $subscription, string $routeName = 'dashboard'): Response
    {
        config()->set('features.saas', true);
        URL::partialMock()
            ->shouldReceive('route')
            ->with('billing.index', [])
            ->andReturn('/billing');

        $organization = (new Organization)->setRelation('activeSubscription', $subscription);
        $user = new class($organization)
        {
            public function __construct(private readonly Organization $organization) {}

            public function resolveCurrentOrganization(): Organization
            {
                return $this->organization;
            }
        };

        $request = Request::create('/dashboard');
        $route = (new Route('GET', '/dashboard', fn () => null))->name($routeName);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        $this->app['url']->setRequest($request);
        $this->app->instance(FeatureResolver::class, new class implements FeatureResolver
        {
            public function enabled(string $feature): bool
            {
                return $feature === 'saas';
            }

            public function enabledForOrg(string $feature, mixed $org): bool
            {
                return false;
            }
        });

        return app(EnsureActiveSubscription::class)->handle(
            $request,
            fn (Request $request): Response => new Response('allowed', Response::HTTP_OK),
        );
    }

    private function subscription(
        string $status = 'active',
        bool $isActive = false,
        bool $isTrialing = false,
        bool $isTrialExpired = false,
    ): SubscriptionContract {
        $subscription = $this->createStub(SubscriptionContract::class);
        $subscription->method('isActive')->willReturn($isActive || $status === 'active');
        $subscription->method('isTrialing')->willReturn($isTrialing);
        $subscription->method('isTrialExpired')->willReturn($isTrialExpired);

        return $subscription;
    }
}
