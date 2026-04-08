<?php

namespace App\Domains\Api\Models;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum API token scoped to an organization.
 *
 * Extends Laravel Sanctum's default token model to add an organization
 * foreign key and a type discriminator (personal vs. organization token).
 *
 * Note: Does NOT use BelongsToOrganization trait because Sanctum must resolve
 * tokens without an active organization context (the org is derived FROM the token).
 *
 * @property int $id
 * @property string $uuid
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<int, string>|null $abilities
 * @property int|null $organization_id
 * @property TokenType $type
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Model $tokenable
 */
class PersonalAccessToken extends SanctumToken
{
    use HasPublicUuid;

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
            $token->type ??= TokenType::Personal;

            if (! $token->organization_id) {
                $currentOrg = app(CurrentOrganization::class);
                if ($currentOrg->isBound()) {
                    $token->organization_id = $currentOrg->id();
                }
            }
        });
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'type' => TokenType::class,
        ]);
    }

    /** @return BelongsTo<Organization, $this> */
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
