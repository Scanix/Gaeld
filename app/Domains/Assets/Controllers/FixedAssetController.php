<?php

namespace App\Domains\Assets\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Actions\DisposeAssetAction;
use App\Domains\Assets\DTOs\CreateFixedAssetData;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Queries\FixedAssetQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fixed asset CRUD, depreciation runs, and disposal handling.
 */
class FixedAssetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FixedAsset::class);

        return Inertia::render('Assets/Index', [
            'assets' => FixedAssetQuery::list($request),
        ]);
    }

    public function create(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', FixedAsset::class);

        return Inertia::render('Assets/Create', [
            'accounts' => Account::where('organization_id', $currentOrg->id())
                ->where('is_active', true)
                ->select('id', 'code', 'name', 'type')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', FixedAsset::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purchase_date' => ['required', 'date'],
            'purchase_amount' => ['required', 'numeric', 'min:0.01'],
            'useful_life_years' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_method' => ['required', 'in:linear,declining_balance'],
            'asset_account_id' => ['required', 'exists:accounts,id'],
            'depreciation_expense_account_id' => ['required', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['required', 'exists:accounts,id'],
        ]);

        $validated['organization_id'] = $currentOrg->id();
        $validated['salvage_value'] = $validated['salvage_value'] ?? '0.00';

        $dto = CreateFixedAssetData::fromArray($validated);

        $asset = FixedAsset::create([
            'organization_id' => $dto->organizationId,
            'name' => $dto->name,
            'description' => $dto->description,
            'purchase_date' => $dto->purchaseDate,
            'purchase_amount' => $dto->purchaseAmount,
            'useful_life_years' => $dto->usefulLifeYears,
            'salvage_value' => $dto->salvageValue,
            'depreciation_method' => $dto->depreciationMethod,
            'asset_account_id' => $dto->assetAccountId,
            'depreciation_expense_account_id' => $dto->depreciationExpenseAccountId,
            'accumulated_depreciation_account_id' => $dto->accumulatedDepreciationAccountId,
        ]);

        return redirect()->route('assets.show', $asset)
            ->with('success', __('app.asset_created'));
    }

    public function show(FixedAsset $asset): Response
    {
        $this->authorize('view', $asset);

        return Inertia::render('Assets/Show', [
            'asset' => $asset->load([
                'assetAccount',
                'depreciationExpenseAccount',
                'accumulatedDepreciationAccount',
                'depreciationEntries.journalEntry',
            ]),
            'totalDepreciated' => $asset->totalDepreciated(),
            'netBookValue' => $asset->netBookValue(),
            'isFullyDepreciated' => $asset->isFullyDepreciated(),
        ]);
    }

    public function edit(FixedAsset $asset): Response
    {
        $this->authorize('update', $asset);

        return Inertia::render('Assets/Edit', [
            'asset' => $asset,
            'accounts' => Account::where('organization_id', $asset->organization_id)
                ->where('is_active', true)
                ->select('id', 'code', 'name', 'type')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purchase_date' => ['required', 'date'],
            'purchase_amount' => ['required', 'numeric', 'min:0.01'],
            'useful_life_years' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_method' => ['required', 'in:linear,declining_balance'],
            'asset_account_id' => ['required', 'exists:accounts,id'],
            'depreciation_expense_account_id' => ['required', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['required', 'exists:accounts,id'],
        ]);

        $asset->update($validated);

        return redirect()->route('assets.show', $asset)
            ->with('success', __('app.asset_updated'));
    }

    public function destroy(FixedAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $asset->delete();

        return redirect()->route('assets.index')
            ->with('success', __('app.asset_deleted'));
    }

    public function depreciate(FixedAsset $asset, DepreciateAssetAction $action): RedirectResponse
    {
        $this->authorize('update', $asset);

        $entry = $action->execute($asset);

        if (! $entry) {
            return redirect()->back()
                ->with('info', __('app.asset_fully_depreciated'));
        }

        return redirect()->route('assets.show', $asset)
            ->with('success', __('app.depreciation_recorded'));
    }

    public function dispose(Request $request, FixedAsset $asset, DisposeAssetAction $action): RedirectResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'disposal_amount' => ['required', 'numeric', 'min:0'],
            'disposal_date' => ['required', 'date'],
        ]);

        $action->execute(
            $asset,
            (string) $validated['disposal_amount'],
            Carbon::parse($validated['disposal_date']),
        );

        return redirect()->route('assets.show', $asset)
            ->with('success', __('app.asset_disposed'));
    }
}
