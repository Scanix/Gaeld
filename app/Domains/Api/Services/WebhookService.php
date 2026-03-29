<?php

namespace App\Domains\Api\Services;

use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Models\WebhookCall;
use App\Support\FeatureFlag;

/**
 * Dispatches webhook events to all active subscribers of an organization
 * and manages delivery retries for failed calls.
 */
class WebhookService
{
    /**
     * Dispatch a webhook event for a given organization.
     *
     * @param  string  $organizationId  The organization UUID
     * @param  string  $event  The event name (e.g. 'invoice.created')
     * @param  array  $payload  The data to send
     */
    public function dispatch(string $organizationId, string $event, array $payload): void
    {
        if (FeatureFlag::disabled('api_access')) {
            return;
        }

        $webhooks = Webhook::query()
            ->withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (! in_array($event, $webhook->events, true)) {
                continue;
            }

            $call = WebhookCall::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => array_merge($payload, [
                    'event' => $event,
                    'timestamp' => now()->toIso8601String(),
                ]),
                'status' => 'pending',
            ]);

            DispatchWebhookJob::dispatch($call);
        }
    }
}
