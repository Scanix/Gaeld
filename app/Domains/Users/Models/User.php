<?php

namespace App\Domains\Users\Models;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements MustVerifyEmail, WebAuthnAuthenticatable
{
    use HasFactory, Notifiable, WebAuthnAuthentication;

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'show_help',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'show_help' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function resolveCurrentOrganization(): ?Organization
    {
        $sessionOrgId = session('current_organization_id');

        if ($sessionOrgId) {
            $org = $this->organizations()->where('organizations.id', $sessionOrgId)->first();
            if ($org) {
                return $org;
            }
            // Stale session value — clear it
            session()->forget('current_organization_id');
        }

        return $this->organizations()->first();
    }

    public function switchOrganization(Organization $organization): void
    {
        session(['current_organization_id' => $organization->id]);
    }
}
