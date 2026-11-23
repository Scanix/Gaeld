<?php

namespace App\Domains\Api\Jobs;

use App\Domains\Api\Models\WebhookCall;
use App\Support\Rules\ValidWebhookUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 300, 3600];

    public function __construct(
        private readonly WebhookCall $webhookCall,
    ) {}

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->webhookCall->id))->dontRelease()->expireAfter(60)];
    }

    public function handle(): void
    {
        $this->webhookCall->refresh();
        if ($this->webhookCall->status === 'success') {
            return;
        }

        $webhook = $this->webhookCall->load('webhook')->webhook;

        if (! $webhook || ! $webhook->is_active || ! in_array($this->webhookCall->event, $webhook->events, true)) {
            $this->webhookCall->update(['status' => 'failed', 'next_retry_at' => null]);

            return;
        }

        $payload = json_encode($this->webhookCall->payload);
        if ($payload === false) {
            $this->webhookCall->update(['status' => 'failed', 'next_retry_at' => null]);

            return;
        }
        $signature = hash_hmac('sha256', $payload, $webhook->secret);

        try {
            $options = ValidWebhookUrl::transportOptions($webhook->url);
            if ($options === null) {
                $this->recordFailure(null, 'Webhook endpoint did not resolve to a public HTTPS destination.');

                return;
            }

            $response = Http::timeout(10)
                ->withOptions($options)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->webhookCall->event,
                    'X-Webhook-Id' => $this->webhookCall->id,
                    'X-Webhook-Event-Id' => $this->webhookCall->payload['event_id'] ?? $this->webhookCall->id,
                    'User-Agent' => 'Gaeld-Webhook/1.0',
                ])
                ->withBody($payload, 'application/json')
                ->post($webhook->url);
        } catch (\Throwable $e) {
            $this->recordFailure(null, $e->getMessage());

            return;
        }

        $webhook->update(['last_triggered_at' => now()]);
        if (! $response->successful()) {
            $this->recordFailure($response->status(), $response->body());

            return;
        }

        $this->webhookCall->update([
            'response_status' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 2000),
            'status' => 'success',
            'attempt' => $this->attempts(),
            'next_retry_at' => null,
        ]);
    }

    private function recordFailure(?int $status, string $reason): void
    {
        $attempt = $this->attempts();
        $delay = $attempt < $this->tries ? ($this->backoff[$attempt - 1] ?? 30) : null;

        $this->webhookCall->update([
            'response_status' => $status,
            'response_body' => mb_substr($reason, 0, 2000),
            'status' => 'failed',
            'attempt' => $attempt,
            'next_retry_at' => $delay === null ? null : now()->addSeconds($delay),
        ]);
        Log::warning('Webhook delivery failed', ['call_id' => $this->webhookCall->id, 'status' => $status]);

        if ($delay !== null) {
            $this->release($delay);
        }
    }
}
