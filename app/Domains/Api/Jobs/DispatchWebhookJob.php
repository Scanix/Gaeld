<?php

namespace App\Domains\Api\Jobs;

use App\Domains\Api\Models\WebhookCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 300, 3600];

    public function __construct(
        private readonly WebhookCall $webhookCall,
    ) {}

    public function handle(): void
    {
        $webhook = $this->webhookCall->webhook;

        if (! $webhook || ! $webhook->is_active) {
            $this->webhookCall->update(['status' => 'failed']);

            return;
        }

        $payload = json_encode($this->webhookCall->payload);
        $signature = hash_hmac('sha256', $payload, $webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->webhookCall->event,
                    'X-Webhook-Id' => $this->webhookCall->id,
                    'User-Agent' => 'Gaeld-Webhook/1.0',
                ])
                ->withBody($payload, 'application/json')
                ->post($webhook->url);

            $this->webhookCall->update([
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'status' => $response->successful() ? 'success' : 'failed',
                'attempt' => $this->attempts(),
            ]);

            $webhook->update(['last_triggered_at' => now()]);

            if (! $response->successful()) {
                Log::warning('Webhook delivery failed', [
                    'webhook_id' => $webhook->id,
                    'call_id' => $this->webhookCall->id,
                    'status' => $response->status(),
                ]);

                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        } catch (\Throwable $e) {
            $this->webhookCall->update([
                'status' => 'failed',
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'attempt' => $this->attempts(),
            ]);

            Log::warning('Webhook delivery exception', [
                'webhook_id' => $webhook->id,
                'call_id' => $this->webhookCall->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        }
    }
}
