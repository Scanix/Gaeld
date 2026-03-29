<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\DepreciationCalculator;
use App\Domains\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DepreciateAssetActionTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private DepreciationCalculator $calculator;

    private DepreciateAssetAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = Mockery::mock(LedgerService::class);
        $this->calculator = Mockery::mock(DepreciationCalculator::class);
        $this->action = new DepreciateAssetAction($this->ledger, $this->calculator);
    }

    public function test_returns_null_for_inactive_asset(): void
    {
        $asset = $this->makeMockAsset(isActive: false);

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_for_disposed_asset(): void
    {
        $asset = $this->makeMockAsset(isActive: true, disposedAt: Carbon::now());

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_for_fully_depreciated_asset(): void
    {
        $asset = $this->makeMockAsset(isActive: true, isFullyDepreciated: true);

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_when_monthly_amount_is_zero(): void
    {
        $asset = $this->makeMockAsset(isActive: true);

        $this->calculator->shouldReceive('monthlyAmount')->once()->with($asset)->andReturn('0.00');

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_clamps_depreciation_to_remaining_depreciable_amount(): void
    {
        [$asset, $journalEntry] = $this->createRealAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
        );

        // Net book value = 10000 - 0 (no prior depreciation) = 10000
        // Remaining depreciable = 10000 - 1000 = 9000
        // But we'll mock netBookValue to 1500 to test clamping: 1500 - 1000 = 500
        $this->calculator->shouldReceive('monthlyAmount')->once()->andReturn('700.00');

        $this->ledger
            ->shouldReceive('postEntry')
            ->once()
            ->withArgs(function ($orgId, $entry) {
                // Verify the amount was clamped to 500.00 (1500 - 1000)
                return $entry->lines[0]->debit === '500.00';
            })
            ->andReturn($journalEntry);

        // Mock netBookValue to simulate partial depreciation
        $mock = Mockery::mock($asset)->makePartial();
        $mock->shouldReceive('netBookValue')->andReturn('1500.00');
        $mock->shouldReceive('isFullyDepreciated')->andReturn(false);

        $result = $this->action->execute($mock, Carbon::parse('2026-01-01'));

        $this->assertInstanceOf(DepreciationEntry::class, $result);
        $this->assertEquals('500.00', $result->amount);
    }

    public function test_posts_journal_entry_and_creates_depreciation_record_on_valid_asset(): void
    {
        [$asset, $journalEntry] = $this->createRealAsset(
            purchaseAmount: '5000.00',
            salvageValue: '500.00',
        );

        $this->calculator->shouldReceive('monthlyAmount')->once()->andReturn('100.00');

        $this->ledger
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        // Mock netBookValue and isFullyDepreciated to control behavior
        $mock = Mockery::mock($asset)->makePartial();
        $mock->shouldReceive('netBookValue')->andReturn('5000.00');
        $mock->shouldReceive('isFullyDepreciated')->andReturn(false);

        $result = $this->action->execute($mock, Carbon::parse('2026-01-01'));

        $this->assertInstanceOf(DepreciationEntry::class, $result);
        $this->assertEquals('100.00', $result->amount);
        $this->assertEquals($journalEntry->id, $result->journal_entry_id);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build a partial Mockery mock of FixedAsset for early-return tests.
     */
    private function makeMockAsset(
        bool $isActive,
        ?Carbon $disposedAt = null,
        bool $isFullyDepreciated = false,
    ): FixedAsset {
        /** @var FixedAsset&MockInterface $asset */
        $asset = Mockery::mock(FixedAsset::class)->makePartial();
        $asset->is_active = $isActive;
        $asset->disposed_at = $disposedAt;

        $asset->shouldReceive('isFullyDepreciated')->andReturn($isFullyDepreciated);
        $asset->shouldReceive('netBookValue')->andReturn('10000.00');

        return $asset;
    }

    /**
     * Create a real FixedAsset and JournalEntry in the DB for integration-style tests.
     *
     * @return array{0: FixedAsset, 1: JournalEntry}
     */
    private function createRealAsset(string $purchaseAmount = '10000.00', string $salvageValue = '1000.00'): array
    {
        $org = Organization::create(['name' => 'Test Org', 'currency' => 'CHF']);

        $assetAccount = Account::create([
            'organization_id' => $org->id,
            'code' => '1500',
            'name' => 'Equipment',
            'type' => AccountType::Asset->value,
        ]);
        $depExpAccount = Account::create([
            'organization_id' => $org->id,
            'code' => '6800',
            'name' => 'Depreciation Expense',
            'type' => AccountType::Expense->value,
        ]);
        $accumDepAccount = Account::create([
            'organization_id' => $org->id,
            'code' => '1509',
            'name' => 'Accumulated Depreciation',
            'type' => AccountType::Asset->value,
        ]);

        $asset = FixedAsset::create([
            'organization_id' => $org->id,
            'name' => 'Test Asset',
            'purchase_date' => '2025-01-01',
            'purchase_amount' => $purchaseAmount,
            'useful_life_years' => 5,
            'salvage_value' => $salvageValue,
            'depreciation_method' => 'linear',
            'asset_account_id' => $assetAccount->id,
            'depreciation_expense_account_id' => $depExpAccount->id,
            'accumulated_depreciation_account_id' => $accumDepAccount->id,
        ]);

        $journalEntry = JournalEntry::create([
            'organization_id' => $org->id,
            'date' => '2026-01-01',
            'reference' => 'DEP-TEST',
            'description' => 'Test depreciation',
            'is_posted' => true,
        ]);

        return [$asset, $journalEntry];
    }
}
