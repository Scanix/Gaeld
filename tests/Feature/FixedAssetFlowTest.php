<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Actions\DisposeAssetAction;
use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Jobs\MonthlyDepreciationJob;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class FixedAssetFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    private Account $assetAccount;

    private Account $depExpAccount;

    private Account $accumDepAccount;

    private Account $bankAccount;

    private Account $gainAccount;

    private Account $lossAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        $this->assetAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1500',
            'name' => 'Equipment',
            'type' => AccountType::Asset->value,
        ]);

        $this->depExpAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '6800',
            'name' => 'Depreciation Expense',
            'type' => AccountType::Expense->value,
        ]);

        $this->accumDepAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1509',
            'name' => 'Accumulated Depreciation',
            'type' => AccountType::Asset->value,
        ]);

        $this->bankAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->gainAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '7510',
            'name' => 'Gain on Asset Disposal',
            'type' => AccountType::Revenue->value,
        ]);

        $this->lossAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '7520',
            'name' => 'Loss on Asset Disposal',
            'type' => AccountType::Expense->value,
        ]);
    }

    private function createAsset(array $overrides = []): FixedAsset
    {
        return FixedAsset::create(array_merge([
            'organization_id' => $this->org->id,
            'name' => 'Office Computer',
            'purchase_date' => '2026-01-01',
            'purchase_amount' => '10000.00',
            'useful_life_years' => 5,
            'salvage_value' => '1000.00',
            'depreciation_method' => DepreciationMethod::Linear->value,
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depExpAccount->id,
            'accumulated_depreciation_account_id' => $this->accumDepAccount->id,
        ], $overrides));
    }

    #[Test]
    public function it_creates_a_fixed_asset_via_route(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post(route('assets.store'), [
                'name' => 'Office Desk',
                'purchase_date' => '2026-02-01',
                'purchase_amount' => '2000.00',
                'useful_life_years' => 10,
                'salvage_value' => '200.00',
                'depreciation_method' => 'linear',
                'asset_account_id' => $this->assetAccount->id,
                'depreciation_expense_account_id' => $this->depExpAccount->id,
                'accumulated_depreciation_account_id' => $this->accumDepAccount->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'name' => 'Office Desk',
            'purchase_amount' => '2000.00',
            'organization_id' => $this->org->id,
        ]);
    }

    #[Test]
    public function depreciation_creates_correct_journal_entry(): void
    {
        $asset = $this->createAsset();

        $action = app(DepreciateAssetAction::class);
        $entry = $action->execute($asset, Carbon::parse('2026-02-01'));

        $this->assertNotNull($entry);
        $this->assertSame('150.00', $entry->amount);

        // Check journal entry is balanced
        $journalEntry = JournalEntry::find($entry->journal_entry_id);
        $this->assertTrue($journalEntry->is_posted);

        $lines = TransactionLine::where('journal_entry_id', $journalEntry->id)->get();
        $this->assertCount(2, $lines);

        // Debit depreciation expense
        $debitLine = $lines->firstWhere('account_id', $this->depExpAccount->id);
        $this->assertSame('150.00', $debitLine->debit);
        $this->assertSame('0.00', $debitLine->credit);

        // Credit accumulated depreciation
        $creditLine = $lines->firstWhere('account_id', $this->accumDepAccount->id);
        $this->assertSame('0.00', $creditLine->debit);
        $this->assertSame('150.00', $creditLine->credit);
    }

    #[Test]
    public function it_skips_fully_depreciated_assets(): void
    {
        $asset = $this->createAsset([
            'purchase_amount' => '1000.00',
            'salvage_value' => '1000.00',
        ]);

        $action = app(DepreciateAssetAction::class);
        $entry = $action->execute($asset);

        $this->assertNull($entry);
    }

    #[Test]
    public function disposal_creates_balanced_entry_with_loss(): void
    {
        $asset = $this->createAsset();

        // Depreciate once first (150.00)
        $depAction = app(DepreciateAssetAction::class);
        $depAction->execute($asset, Carbon::parse('2026-02-01'));

        $asset->refresh();

        // Dispose for 8000 (NBV = 10000 - 150 = 9850, loss = 8000 - 9850 = -1850)
        $disposeAction = app(DisposeAssetAction::class);
        $disposedAsset = $disposeAction->execute($asset, '8000.00', Carbon::parse('2026-03-01'));

        $this->assertFalse($disposedAsset->is_active);
        $this->assertNotNull($disposedAsset->disposed_at);
        $this->assertSame('8000.00', $disposedAsset->disposal_amount);

        // Check balanced entry
        $journalEntry = JournalEntry::where('reference', 'DISP-'.$asset->id)->first();
        $this->assertNotNull($journalEntry);
        $this->assertTrue($journalEntry->isBalanced());
    }

    #[Test]
    public function disposal_creates_balanced_entry_with_gain(): void
    {
        $asset = $this->createAsset([
            'purchase_amount' => '5000.00',
            'salvage_value' => '500.00',
        ]);

        // Depreciate once (monthly: (5000-500)/5/12 = 75.00)
        $depAction = app(DepreciateAssetAction::class);
        $depAction->execute($asset, Carbon::parse('2026-02-01'));

        $asset->refresh();

        // Dispose for 5500 (NBV = 5000 - 75 = 4925, gain = 5500 - 4925 = 575)
        $disposeAction = app(DisposeAssetAction::class);
        $disposedAsset = $disposeAction->execute($asset, '5500.00', Carbon::parse('2026-03-01'));

        $this->assertFalse($disposedAsset->is_active);

        $journalEntry = JournalEntry::where('reference', 'DISP-'.$asset->id)->first();
        $this->assertNotNull($journalEntry);
        $this->assertTrue($journalEntry->isBalanced());
    }

    #[Test]
    public function monthly_job_processes_active_assets(): void
    {
        $asset1 = $this->createAsset(['name' => 'Computer A']);
        $asset2 = $this->createAsset(['name' => 'Computer B']);
        $disposed = $this->createAsset([
            'name' => 'Old Printer',
            'is_active' => false,
            'disposed_at' => now(),
        ]);

        $job = app(MonthlyDepreciationJob::class);
        $job->handle(app(DepreciateAssetAction::class));

        // Both active assets should have depreciation entries
        $this->assertSame(1, $asset1->depreciationEntries()->count());
        $this->assertSame(1, $asset2->depreciationEntries()->count());
        $this->assertSame(0, $disposed->depreciationEntries()->count());
    }
}
