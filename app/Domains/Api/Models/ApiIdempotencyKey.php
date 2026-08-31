<?php

namespace App\Domains\Api\Models;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ApiIdempotencyKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'request_key',
        'http_method',
        'route',
        'payload_hash',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return Carbon::parse($this->expires_at)->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }
}
