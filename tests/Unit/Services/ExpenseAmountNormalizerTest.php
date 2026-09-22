<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\Enums\ExpenseAmountBasis;
use App\Domains\Expenses\Services\ExpenseAmountNormalizer;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAmountNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_a_gross_amount_to_net_and_vat(): void
    {
        $organization = Organization::factory()->create();
        $vatRate = VatRate::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Standard',
            'rate' => '8.10',
            'code' => 'NORMAL',
        ]);

        $breakdown = app(ExpenseAmountNormalizer::class)->normalize(
            $organization->id,
            '50.00',
            (string) $vatRate->id,
            ExpenseAmountBasis::Gross,
        );

        $this->assertSame('46.25', $breakdown->netAmount);
        $this->assertSame('3.75', $breakdown->vatAmount);
        $this->assertSame('50.00', $breakdown->grossAmount);
    }

    public function test_it_preserves_net_input_for_existing_integrations(): void
    {
        $organization = Organization::factory()->create();
        $vatRate = VatRate::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Standard',
            'rate' => '8.10',
            'code' => 'NORMAL',
        ]);

        $breakdown = app(ExpenseAmountNormalizer::class)->normalize(
            $organization->id,
            '50.00',
            (string) $vatRate->id,
            ExpenseAmountBasis::Net,
        );

        $this->assertSame('50.00', $breakdown->netAmount);
        $this->assertSame('4.05', $breakdown->vatAmount);
        $this->assertSame('54.05', $breakdown->grossAmount);
    }

    public function test_it_does_not_add_vat_without_a_rate(): void
    {
        $organization = Organization::factory()->create();

        $breakdown = app(ExpenseAmountNormalizer::class)->normalize(
            $organization->id,
            '50.00',
            null,
            ExpenseAmountBasis::Gross,
        );

        $this->assertSame('50.00', $breakdown->netAmount);
        $this->assertSame('0.00', $breakdown->vatAmount);
        $this->assertSame('50.00', $breakdown->grossAmount);
    }
}
