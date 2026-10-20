<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Services\CurrencyConversionService;
use App\Domains\Organizations\Models\Organization;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_uses_organization_scoped_historical_rates_and_inverse_rates(): void
    {
        $organization = Organization::factory()->create();
        foreach (['2026-03-01' => '0.95', '2026-04-01' => '0.96'] as $date => $rate) {
            ExchangeRate::create([
                'organization_id' => $organization->id,
                'currency_from' => 'EUR',
                'currency_to' => 'CHF',
                'rate' => $rate,
                'date' => $date,
                'source' => 'manual',
            ]);
        }
        $service = app(CurrencyConversionService::class);

        $this->assertSame('95.00', $service->convert($organization->id, 'EUR', 'CHF', '100.00', '2026-03-15')['amount']);
        $this->assertSame('100.00', $service->convert($organization->id, 'CHF', 'EUR', '95.00', '2026-03-15')['amount']);
        $this->assertSame('100.00', $service->convert($organization->id, 'CHF', 'CHF', '100.00', '2026-01-01')['amount']);

        $this->expectException(DomainException::class);
        $service->rate(Organization::factory()->create()->id, 'EUR', 'CHF', '2026-03-15');
    }

    public function test_zero_rate_cannot_erase_a_foreign_amount(): void
    {
        $organization = Organization::factory()->create();
        ExchangeRate::create([
            'organization_id' => $organization->id,
            'currency_from' => 'EUR',
            'currency_to' => 'CHF',
            'rate' => '0',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);

        $this->expectException(DomainException::class);
        app(CurrencyConversionService::class)->convert($organization->id, 'EUR', 'CHF', '100.00', '2026-03-15');
    }
}
