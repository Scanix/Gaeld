<?php

namespace App\Domains\Assets\Jobs;

use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Models\FixedAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonthlyDepreciationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DepreciateAssetAction $action): void
    {
        FixedAsset::query()
            ->where('is_active', true)
            ->whereNull('disposed_at')
            ->each(function (FixedAsset $asset) use ($action) {
                if (! $asset->isFullyDepreciated()) {
                    $action->execute($asset);
                }
            });
    }
}
