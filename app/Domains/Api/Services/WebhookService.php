<?php

namespace App\Domains\Api\Services;

use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Models\WebhookCall;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Support\FeatureFlag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dispatches webhook events to all active subscribers of an organization
 * and manages delivery retries for failed calls.
 */
class WebhookService
{
    public function dispatchInvoice(Invoice $invoice, WebhookEvent $event, ?InvoicePayment $payment = null): void
    {
        $payload = ['invoice_id' => $invoice->id, 'organization_id' => $invoice->organization_id];

        if ($payment !== null) {
            $payload['payment_id'] = $payment->id;
        }

        $this->dispatch($invoice->organization_id, $event->value, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $organizationId, string $event, array $payload): void
    {
        $webhooks = Webhook::query()
            ->withoutGlobalScope('organization')
            ->with('organization')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        $subscriber = $webhooks->first();
        if ($subscriber === null || ! FeatureFlag::enabledForOrg('api_access', $subscriber->organization)) {
            return;
        }

        $payload = array_merge($payload, [
            'event' => $event,
            'event_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
        ]);

        foreach ($webhooks as $webhook) {
            $call = WebhookCall::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            DB::afterCommit(static fn () => DispatchWebhookJob::dispatch($call));
        }
    }
}
