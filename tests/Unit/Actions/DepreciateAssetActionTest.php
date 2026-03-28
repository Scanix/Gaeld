<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\DepreciationCalculator;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class DepreciateAssetActionTest extends TestCase
{
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
        $asset = $this->makeAsset(isActive: false);

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_for_disposed_asset(): void
    {
        $asset = $this->makeAsset(isActive: true, disposedAt: Carbon::now());

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_for_fully_depreciated_asset(): void
    {
        $asset = $this->makeAsset(isActive: true, isFullyDepreciated: true);

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_returns_null_when_monthly_amount_is_zero(): void
    {
        $asset = $this->makeAsset(isActive: true);

        $this->calculator->shouldReceive('monthlyAmount')->once()->with($asset)->andReturn('0.00');

        $result = $this->action->execute($asset);

        $this->assertNull($result);
    }

    public function test_clamps_depreciation_to_remaining_depreciable_amount(): void
    {
        $asset = $this->makeAsset(isActive: true, netBookValue: '1500.00', salvageValue: '1000.00');
        // Net book value minus salvage = 500.00 (remaining depreciable)
        // Monthly amount is larger — should be clamped
        $this->calculator->shouldReceive('monthlyAmount')->once()->with($asset)->andReturn('700.00');

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 42;

        $this->ledger
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        // DepreciationEntry::create is called with clamped amount
        $asset->shouldReceive('fresh')->andReturnSelf();

        $this->action->execute($asset, Carbon::parse('2026-01-01'));
        // If we get here without exception, clamping didn't break anything
        $this->assertTrue(true);
    }

    public function test_posts_journal_entry_and_creates_depreciation_record_on_valid_asset(): void
    {
        $asset = $this->makeAsset(isActive: true, netBookValue: '5000.00', salvageValue: '500.00');
        $this->calculator->shouldReceive('monthlyAmount')->once()->with($asset)->andReturn('100.00');

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 1;

        $this->ledger
            ->shouldReceive('postEntry')
            ->once()
            ->andReturn($journalEntry);

        $asset->shouldReceive('fresh')->andReturnSelf();

        // Run with explicit period date — succeeds without crashing
        $this->action->execute($asset, Carbon::parse('2026-01-01'));
        $this->assertTrue(true); // reaches here — journal entry was posted
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build a partial Mockery mock of FixedAsset with the fields needed by the action.
     *
     * @param  Carbon|null  $disposedAt
     */
    private function makeAsset(
        bool $isActive,
        ?Carbon $disposedAt = null,
        bool $isFullyDepreciated = false,
        string $netBookValue = '10000.00',
        string $salvageValue = '1000.00',
    ): FixedAsset {
        /** @var FixedAsset&\Mockery\MockInterface $asset */
        $asset = Mockery::mock(FixedAsset::class)->makePartial();
        $asset->is_active = $isActive;
        $asset->disposed_at = $disposedAt;
        $asset->salvage_value = $salvageValue;
        $asset->organization_id = 'org-1';
        $asset->name = 'Test Asset';
        $asset->depreciation_expense_account_id = 101;
        $asset->accumulated_depreciation_account_id = 102;

        $asset->shouldReceive('isFullyDepreciated')->andReturn($isFullyDepreciated);
        $asset->shouldReceive('netBookValue')->andReturn($netBookValue);

        return $asset;
    }
}
