<?php

namespace App\Domains\Assets\Queries;

use App\Domains\Assets\Models\FixedAsset;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class FixedAssetQuery
{
    public static function list(Request $request, int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for(
            FixedAsset::query()->with([
                'assetAccount',
                'depreciationExpenseAccount',
                'accumulatedDepreciationAccount',
            ]),
            $request,
        )
            ->allowedSorts(['name', 'purchase_date', 'purchase_amount', 'created_at'], 'purchase_date', 'desc')
            ->allowedFilters(['depreciation_method'])
            ->searchable(['name', 'description'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
