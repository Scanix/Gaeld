<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Users\Models\User;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending invitation for a user to join an organization.
 *
 * Stores a hashed token (the plain-text version is only available
 * transiently after creation for the notification e-mail), an expiry
 * date, and acceptance state.
 */
class OrganizationInvitation extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    /**
     * Transient plain-text token set after creation for notification purposes.
     * Not persisted — the hashed token is stored in `token` column.
     */
    public ?string $plain_token = null;

    protected $fillable = [
        'organization_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
