<?php

namespace App\Domains\Api\Models;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum API token scoped to an organization.
 *
 * Extends Laravel Sanctum's default token model to add an organization
 * foreign key and a type discriminator (personal vs. organization token).
 */
class PersonalAccessToken extends SanctumToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'organization_id',
        'type',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $token) {
            if (! $token->organization_id) {
                $currentOrg = app(CurrentOrganization::class);
                if ($currentOrg->isBound()) {
                    $token->organization_id = $currentOrg->id();
                }
            }
            $token->type ??= TokenType::Personal;
        });
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'type' => TokenType::class,
        ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === TokenType::Personal;
    }

    public function isOrganization(): bool
    {
        return $this->type === TokenType::Organization;
    }

    public function scopePersonal(Builder $query): Builder
    {
        return $query->where('type', TokenType::Personal);
    }

    public function scopeOrganization(Builder $query): Builder
    {
        return $query->where('type', TokenType::Organization);
    }
}
