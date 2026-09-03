<?php

namespace App\Console\Commands;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use App\Http\Middleware\Api\TokenPermissionMap;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GaeldTokenCommand extends Command
{
    protected $signature = 'gaeld:token
        {organization : Organization UUID}
        {--user= : Owner email address; defaults to the first organization owner}
        {--name=Provisioning token : Descriptive token name}
        {--abilities=* : Token ability; repeat the option for multiple abilities}
        {--expires-in-days= : Number of days until the token expires (1-365)}';

    protected $description = 'Create an organization-scoped API token for an installed Gäld organization';

    public function handle(): int
    {
        $organization = Organization::query()->find($this->argument('organization'));

        if ($organization === null) {
            $this->components->error('Organization not found.');

            return self::FAILURE;
        }

        $user = $this->resolveOwner($organization, $this->option('user'));
        if ($user === null) {
            $this->components->error('No matching organization owner was found.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $this->components->error('The token name cannot be empty.');

            return self::FAILURE;
        }

        $requestedAbilities = array_values(array_filter(
            array_map(static fn (mixed $ability): string => trim((string) $ability), (array) $this->option('abilities')),
            static fn (string $ability): bool => $ability !== '',
        ));
        $invalidAbilities = array_values(array_diff($requestedAbilities, TokenPermissionMap::acceptedAbilities()));

        if ($invalidAbilities !== []) {
            $this->components->error('Unknown token ability: '.implode(', ', $invalidAbilities));

            return self::FAILURE;
        }

        $expiresAt = $this->resolveExpiration();
        if ($expiresAt === false) {
            return self::FAILURE;
        }

        $abilities = TokenPermissionMap::normalize($requestedAbilities);
        $abilities = $abilities === [] ? ['*'] : $abilities;

        app(CurrentOrganization::class)->set($organization);
        $token = $user->createToken($name, $abilities, $expiresAt);
        $token->accessToken->update([
            'organization_id' => $organization->id,
            'type' => TokenType::Organization,
        ]);

        $this->components->info('API token created successfully.');
        $this->components->twoColumnDetail('Organization', $organization->name);
        $this->components->twoColumnDetail('User', $user->email);
        $this->components->twoColumnDetail('Abilities', implode(', ', $abilities));
        $this->components->twoColumnDetail('Expires at', $expiresAt?->toIso8601String() ?? 'Never');
        $this->components->twoColumnDetail('Token', $token->plainTextToken);

        return self::SUCCESS;
    }

    private function resolveOwner(Organization $organization, mixed $email): ?User
    {
        return $organization->users()
            ->wherePivot('role', 'owner')
            ->when($email !== null, fn ($query) => $query->where('users.email', trim((string) $email)))
            ->orderBy('users.id')
            ->first();
    }

    private function resolveExpiration(): Carbon|false|null
    {
        $value = $this->option('expires-in-days');

        if ($value === null || $value === '') {
            return null;
        }

        if (! ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 365) {
            $this->components->error('The expiration must be an integer between 1 and 365 days.');

            return false;
        }

        return now()->addDays((int) $value);
    }
}
