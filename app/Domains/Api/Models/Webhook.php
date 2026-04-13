<?php

namespace App\Domains\Api\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Outbound webhook endpoint registered by an organization.
 *
 * Subscribes to one or more event types and delivers signed payloads
 * to the configured URL. The shared secret is stored encrypted.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $url
 * @property string $secret
 * @property array<int, mixed> $events
 * @property bool $is_active
 * @property Carbon|null $last_triggered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, WebhookCall> $calls
 */
class Webhook extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'url',
        'secret',
        'events',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<WebhookCall, $this> */
    public function calls(): HasMany
    {
        return $this->hasMany(WebhookCall::class);
    }

    /** Generate a cryptographically random webhook secret. */
    public static function generateSecret(): string
    {
        return Str::random(64);
    }
}
