<?php

namespace App\Domains\Api\Models;

use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Delivery attempt log for a webhook event.
 *
 * Each row represents one HTTP call to the parent Webhook's URL,
 * tracking the response status, body, retry state, and attempt number.
 *
 * @property string $id
 * @property string $webhook_id
 * @property string $event
 * @property array $payload
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string $status
 * @property int $attempt
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Webhook $webhook
 */
class WebhookCall extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_status',
        'response_body',
        'status',
        'attempt',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'attempt' => 'integer',
            'next_retry_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
