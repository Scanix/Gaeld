<?php

namespace App\Domains\Assets\Jobs;

use App\Domains\Assets\Actions\DepreciateAssetAction;
use App\Domains\Assets\Models\FixedAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonthlyDepreciationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public function handle(DepreciateAssetAction $action): void
    {
        FixedAsset::query()
            ->where('is_active', true)
            ->whereNull('disposed_at')
            ->each(function (FixedAsset $asset) use ($action) {
                if (! $asset->isFullyDepreciated()) {
                    try {
                        $action->execute($asset);
                    } catch (\Throwable $e) {
                        Log::error('MonthlyDepreciationJob: failed to depreciate asset', [
                            'asset_id' => $asset->getKey(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('MonthlyDepreciationJob: job exhausted all retries', [
            'error' => $e->getMessage(),
        ]);
    }
}
