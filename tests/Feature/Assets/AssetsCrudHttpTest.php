<?php

namespace Tests\Feature\Assets;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Assets\Models\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class AssetsCrudHttpTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Account $assetAccount;

    private Account $depreciationAccount;

    private Account $accumulatedAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->assetAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1500',
            'name' => 'Machinery',
            'type' => AccountType::Asset,
            'is_active' => true,
        ]);

        $this->depreciationAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '6800',
            'name' => 'Depreciation Expense',
            'type' => AccountType::Expense,
            'is_active' => true,
        ]);

        $this->accumulatedAccount = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1509',
            'name' => 'Accumulated Depreciation',
            'type' => AccountType::Asset,
            'is_active' => true,
        ]);
    }

    public function test_asset_index_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/assets')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Assets/Index'));
    }

    public function test_asset_create_page_provides_accounts(): void
    {
        $this->actingAs($this->user)
            ->get('/assets/create')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Assets/Create')
                ->has('accounts')
            );
    }

    public function test_asset_store_creates_record(): void
    {
        $this->actingAs($this->user)
            ->post('/assets', [
                'name' => 'Office Laptop',
                'purchase_date' => '2026-01-15',
                'purchase_amount' => '2500.00',
                'useful_life_years' => 3,
                'salvage_value' => '100.00',
                'depreciation_method' => 'linear',
                'asset_account_id' => $this->assetAccount->id,
                'depreciation_expense_account_id' => $this->depreciationAccount->id,
                'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'organization_id' => $this->org->id,
            'name' => 'Office Laptop',
            'purchase_amount' => '2500.00',
        ]);
    }

    public function test_asset_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post('/assets', [])
            ->assertSessionHasErrors([
                'name',
                'purchase_date',
                'purchase_amount',
                'useful_life_years',
                'depreciation_method',
                'asset_account_id',
                'depreciation_expense_account_id',
                'accumulated_depreciation_account_id',
            ]);
    }

    public function test_asset_show_includes_depreciation_data(): void
    {
        $asset = FixedAsset::create([
            'organization_id' => $this->org->id,
            'name' => 'Server',
            'purchase_date' => '2026-01-01',
            'purchase_amount' => '5000.00',
            'useful_life_years' => 5,
            'salvage_value' => '500.00',
            'depreciation_method' => 'linear',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $this->actingAs($this->user)
            ->get("/assets/{$asset->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Assets/Show')
                ->has('asset')
                ->has('netBookValue')
                ->has('isFullyDepreciated')
            );
    }

    public function test_asset_update_changes_fields(): void
    {
        $asset = FixedAsset::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'purchase_date' => '2026-01-01',
            'purchase_amount' => '5000.00',
            'useful_life_years' => 5,
            'salvage_value' => '500.00',
            'depreciation_method' => 'linear',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $this->actingAs($this->user)
            ->put("/assets/{$asset->id}", [
                'name' => 'New Name',
                'description' => 'Updated description',
                'purchase_date' => '2026-01-01',
                'purchase_amount' => '5000.00',
                'useful_life_years' => 5,
                'salvage_value' => '500.00',
                'depreciation_method' => 'linear',
                'asset_account_id' => $this->assetAccount->id,
                'depreciation_expense_account_id' => $this->depreciationAccount->id,
                'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'name' => 'New Name',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_assets(): void
    {
        $this->get('/assets')->assertRedirect('/login');
    }
}
