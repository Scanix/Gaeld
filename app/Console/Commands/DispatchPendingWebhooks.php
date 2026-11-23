<?php

namespace App\Console\Commands;

use App\Domains\Api\Models\WebhookCall;
use App\Domains\Api\Services\WebhookService;
use Illuminate\Console\Command;

class DispatchPendingWebhooks extends Command
{
    protected $signature = 'webhooks:dispatch-pending {--limit=100}';

    protected $description = 'Retry due webhook calls that have not reached the delivery queue';

    public function handle(WebhookService $webhooks): int
    {
        $calls = WebhookCall::query()->where('status', 'pending')->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get();
        $status = self::SUCCESS;
        foreach ($calls as $call) {
            if (! $webhooks->enqueuePending($call)) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
