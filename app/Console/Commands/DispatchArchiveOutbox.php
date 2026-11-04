<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Jobs\ProcessArchivePackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchArchiveOutbox extends Command
{
    protected $signature = 'archive:dispatch {--limit=100}';

    protected $description = 'Dispatch committed archive requests, reclaiming interrupted dispatch leases';

    public function handle(ArchiveAccessLog $audit): int
    {
        ArchivePackage::withoutGlobalScopes()->whereIn('id', ArchiveOutbox::query()->select('archive_package_id')
            ->whereNull('completed_at')->where('attempts', '>=', 3)
            ->where(fn ($query) => $query->where('dispatched_at', '<=', now()->subMinutes(40))
                ->orWhere(fn ($undispatched) => $undispatched->whereNull('dispatched_at')->where('available_at', '<=', now()))))
            ->whereNotIn('state', ['verified', 'failed', 'incident'])
            ->eachById(function (ArchivePackage $candidate) use ($audit): void {
                DB::transaction(function () use ($candidate, $audit): void {
                    $package = ArchivePackage::withoutGlobalScopes()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    $outbox = ArchiveOutbox::query()->where('archive_package_id', $package->id)->firstOrFail();
                    if ($outbox->completed_at || $outbox->attempts < 3 || in_array($package->state, ['verified', 'failed', 'incident'], true)) {
                        return;
                    }
                    $package->update(['state' => $package->manifest_sha256 ? 'incident' : 'failed', 'failure' => 'Archive dispatch or processing repeatedly stopped. Review the incident and resume the package.']);
                    $audit->record($package->organization_id, $package->id, 'package.dispatch_exhausted', metadata: ['attempts' => $outbox->attempts]);
                });
            });
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $failed = false;
        for ($i = 0; $i < $limit; $i++) {
            $item = DB::transaction(function (): ?ArchiveOutbox {
                $item = ArchiveOutbox::query()->whereNull('completed_at')->where('attempts', '<', 3)
                    ->where('available_at', '<=', now())
                    ->where(fn ($query) => $query->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', now()->subMinutes(40)))
                    ->orderBy('available_at')->lock('FOR UPDATE SKIP LOCKED')->first();
                if ($item !== null) {
                    $item->update(['dispatched_at' => now(), 'attempts' => $item->attempts + 1]);
                }

                return $item;
            });
            if ($item === null) {
                break;
            }
            try {
                ProcessArchivePackage::dispatch($item->archive_package_id);
            } catch (\Throwable $exception) {
                $item->update(['dispatched_at' => null, 'available_at' => now()->addMinute()]);
                report($exception);
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
