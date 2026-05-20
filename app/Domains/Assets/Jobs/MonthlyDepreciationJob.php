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
        // This job runs outside the HTTP request lifecycle. The BelongsToOrganization
        // global scope on FixedAsset is a no-op here (CurrentOrganization::isBound()
        // returns false in a queue worker), so we intentionally query all
        // organisations' assets in a single pass. Do NOT inject CurrentOrganization
        // here — DepreciateAssetAction handles per-asset org context internally.
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
