<?php

namespace Tests\Unit\Middleware;

use App\Domains\Organizations\Models\Organization;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Support\Contracts\FeatureResolver;
use App\Support\Contracts\SubscriptionContract;
use Illuminate\Http\Request;
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

    private function runMiddleware(SubscriptionContract $subscription): Response
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
        $subscription = $this->createMock(SubscriptionContract::class);
        $subscription->method('isActive')->willReturn($isActive || $status === 'active');
        $subscription->method('isTrialing')->willReturn($isTrialing);
        $subscription->method('isTrialExpired')->willReturn($isTrialExpired);

        return $subscription;
    }
}
