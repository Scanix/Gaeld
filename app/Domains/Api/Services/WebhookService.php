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
                'next_retry_at' => now(),
            ]);

            DB::afterCommit(fn () => $this->enqueuePending($call));
        }
    }

    /** Enqueue a committed delivery once, with a lease for an interrupted publisher. */
    public function enqueuePending(WebhookCall $call): bool
    {
        try {
            $pending = WebhookCall::query()->whereKey($call->id)->where('status', 'pending');
            $lease = now()->addMinute();
            if ((clone $pending)->where('next_retry_at', '<=', now())->update(['next_retry_at' => $lease]) === 0) {
                return true;
            }

            DispatchWebhookJob::dispatch($call);
            $pending->where('next_retry_at', $lease)->update(['next_retry_at' => null]);

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
