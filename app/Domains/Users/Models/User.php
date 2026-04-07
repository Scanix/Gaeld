<?php

namespace App\Domains\Users\Models;

use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Authenticated user account.
 *
 * Belongs to one or more organizations via the `organization_users` pivot table.
 * Supports two-factor authentication (TOTP & WebAuthn), Sanctum API tokens,
 * role-based permissions (Spatie), and per-user locale preference.
 *
 * @property Carbon|null $email_change_requested_at
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, WebAuthnAuthenticatable
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, WebAuthnAuthentication;

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'show_help',
        'dashboard_layout',
        'accepted_privacy_at',
        'accepted_terms_at',
        'pending_email',
        'email_change_token',
        'email_change_requested_at',
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
            'dashboard_layout' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'accepted_privacy_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'email_change_requested_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** @return BelongsToMany<Organization, $this> */
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

    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
